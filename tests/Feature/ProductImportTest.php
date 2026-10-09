<?php

use App\Models\Product;
use App\Models\ProductPriceHistory;
use Illuminate\Support\Facades\DB;

test('scraped products are imported and existing products are updated', function () {
    $csvPath = tempnam(sys_get_temp_dir(), 'products-');

    file_put_contents($csvPath, implode("\n", [
        'title,store,original_price,current_price,unit_price,unit',
        'Milk,etop.lv,"1,20 € - 1,50 €",0.99,0.99,€/kg',
    ]));

    $this->artisan('products:import', ['file' => $csvPath])
        ->assertSuccessful()
        ->expectsOutput('Imported 1 products.');

    expect(Product::query()->count())->toBe(1)
        ->and(Product::first()->store)->toBe('etop.lv')
        ->and(Product::first()->current_price)->toBe('0.99')
        ->and(Product::first()->unit_price)->toBe('0.99')
        ->and(Product::first()->unit)->toBe('€/kg');

    file_put_contents($csvPath, implode("\n", [
        'title,store,original_price,current_price,unit_price,unit',
        'Milk,etop.lv,"1,20 € - 1,50 €",0.89,0.89,€/kg',
    ]));

    $this->artisan('products:import', ['file' => $csvPath])
        ->assertSuccessful();

    expect(Product::query()->count())->toBe(1)
        ->and(Product::first()->current_price)->toBe('0.89');

    unlink($csvPath);
});

test('imported products get a category and existing products can be categorized', function () {
    $csvPath = tempnam(sys_get_temp_dir(), 'products-');

    file_put_contents($csvPath, implode("\n", [
        'title,store,original_price,current_price,unit_price,unit',
        "ČIPSI LAY'S AR SIERA GARŠU 180G,etop.lv,,1.99,11.06,€/kg",
    ]));

    $this->artisan('products:import', ['file' => $csvPath])->assertSuccessful();

    expect(Product::first()->category)->toBe('Uzkodas, rieksti un sēklas');

    unlink($csvPath);

    $milk = Product::query()->create(['title' => 'PIENS OPĀ 2.5% 0.9L', 'store' => 'etop.lv']);

    $this->artisan('products:categorize')->assertSuccessful();

    expect($milk->fresh()->category)->toBe('Piena produkti un olas');
});

test('a CSV missing scraper columns is rejected', function () {
    $csvPath = tempnam(sys_get_temp_dir(), 'products-');

    file_put_contents($csvPath, implode("\n", [
        'title,original_price,current_price',
        'Bread,1.50,0.99',
    ]));

    expect(fn () => $this->artisan('products:import', ['file' => $csvPath])->run())
        ->toThrow(RuntimeException::class, 'The product CSV is missing columns: store, unit_price, unit.');

    expect(Product::query()->count())->toBe(0);

    unlink($csvPath);
});

test('values holding only invisible characters are imported as empty', function () {
    $csvPath = tempnam(sys_get_temp_dir(), 'products-');

    file_put_contents($csvPath, implode("\n", [
        'title,store,original_price,current_price,unit_price,unit',
        "Milk,etop.lv,\u{200C},0.99,,\u{FEFF} ",
    ]));

    $this->artisan('products:import', ['file' => $csvPath])->assertSuccessful();

    expect(Product::query()->sole())
        ->original_price->toBeNull()
        ->unit->toBeNull();

    unlink($csvPath);
});

test('columns are read by name and image_url is optional', function () {
    $csvPath = tempnam(sys_get_temp_dir(), 'products-');

    file_put_contents($csvPath, implode("\n", [
        'store,title,current_price,original_price,unit,unit_price,image_url',
        'rimi.lv,Milk,0.99,1.50,€/l,0.99,https://example.com/milk.jpg',
    ]));

    $this->artisan('products:import', ['file' => $csvPath])->assertSuccessful();

    expect(Product::query()->sole())
        ->title->toBe('Milk')
        ->store->toBe('rimi.lv')
        ->current_price->toBe('0.99')
        ->original_price->toBe('1.50')
        ->image_url->toBe('https://example.com/milk.jpg');

    unlink($csvPath);
});

test('the same product can be imported for different stores', function () {
    foreach (['etop.lv', 'maxima.lv'] as $store) {
        $csvPath = tempnam(sys_get_temp_dir(), 'products-');
        file_put_contents($csvPath, implode("\n", [
            'title,store,original_price,current_price,unit_price,unit',
            "Milk,{$store},1.50,0.99,0.99,€/l",
        ]));

        $this->artisan('products:import', ['file' => $csvPath])
            ->assertSuccessful();

        unlink($csvPath);
    }

    expect(Product::query()->where('title', 'Milk')->count())->toBe(2)
        ->and(Product::query()->where('title', 'Milk')->pluck('store')->sort()->values()->all())
        ->toBe(['etop.lv', 'maxima.lv']);
});

test('all products can be added to price history without duplicates', function () {
    Product::query()->create([
        'title' => 'Milk',
        'store' => 'etop.lv',
        'current_price' => 0.99,
    ]);
    Product::query()->create([
        'title' => 'Bread',
        'store' => 'maxima.lv',
        'current_price' => 1.49,
    ]);
    Product::query()->create([
        'title' => 'No Price',
        'store' => 'etop.lv',
    ]);

    $this->artisan('products:backfill-price-history')
        ->assertSuccessful()
        ->expectsOutput('Added 2 product price-history snapshots.');

    expect(ProductPriceHistory::query()->count())->toBe(2)
        ->and(ProductPriceHistory::query()->pluck('previous_price')->sort()->values()->all())
        ->toBe(['0.99', '1.49']);

    $this->artisan('products:backfill-price-history')
        ->assertSuccessful()
        ->expectsOutput('Added 0 product price-history snapshots.');

    expect(ProductPriceHistory::query()->count())->toBe(2);
});

test('products missing from a store\'s new import are marked as ended, other stores are untouched', function () {
    importCsv("Milk,rimi.lv,,0.99,,\nBread,rimi.lv,,1.29,,\nMilk,maxima.lv,,1.09,,");

    $this->travel(1)->day();
    importCsv('Milk,rimi.lv,,0.89,,');

    expect(Product::query()->where(['title' => 'Bread', 'store' => 'rimi.lv'])->sole()->offerHasEnded())->toBeTrue()
        ->and(Product::query()->where(['title' => 'Milk', 'store' => 'rimi.lv'])->sole()->offerHasEnded())->toBeFalse()
        ->and(Product::query()->where(['title' => 'Milk', 'store' => 'maxima.lv'])->sole()->offerHasEnded())->toBeFalse()
        ->and(Product::query()->onOffer()->count())->toBe(2);
});

test('an ended offer that comes back is on offer again', function () {
    importCsv("Milk,rimi.lv,,0.99,,\nBread,rimi.lv,,1.29,,");
    $this->travel(1)->day();
    importCsv('Milk,rimi.lv,,0.99,,');
    $this->travel(1)->day();
    importCsv("Milk,rimi.lv,,0.99,,\nBread,rimi.lv,,1.19,,");

    expect(Product::query()->where('title', 'Bread')->sole())
        ->offerHasEnded()->toBeFalse()
        ->current_price->toBe('1.19');
});

test('a file with far fewer products than the store has on offer is refused and changes nothing', function () {
    foreach (range(1, 30) as $i) {
        Product::query()->create(['title' => "Prece {$i}", 'store' => 'rimi.lv', 'current_price' => 1.00]);
    }
    $csvPath = tempnam(sys_get_temp_dir(), 'products-');
    file_put_contents($csvPath, "title,store,original_price,current_price,unit_price,unit\nPrece 1,rimi.lv,,0.50,,\n");

    expect(fn () => $this->artisan('products:import', ['file' => $csvPath])->run())
        ->toThrow(RuntimeException::class, 'The file has 1 rimi.lv products, but 30 are on offer now');

    expect(Product::query()->onOffer()->count())->toBe(30)
        ->and(Product::query()->where('title', 'Prece 1')->sole()->current_price)->toBe('1.00')
        ->and(ProductPriceHistory::query()->count())->toBe(0);

    // A real drop in offers can still be imported on purpose.
    $this->travel(1)->day();
    $this->artisan('products:import', ['file' => $csvPath, '--force' => true])->assertSuccessful();

    expect(Product::query()->onOffer()->count())->toBe(1);

    unlink($csvPath);
});

test('a failure halfway through the import leaves prices, ended offers and history untouched', function () {
    importCsv("Piens,rimi.lv,,1.39,,\nMaize,rimi.lv,,0.99,,");
    $this->travel(1)->day();

    // Make saving the price history fail after prices were written and offers ended. Not by
    // renaming the table: MySQL commits the open transaction on any table change, so the
    // rollback being tested would never happen there.
    DB::beforeExecuting(function (string $query): void {
        if (preg_match('/^insert into .product_price_histories./i', $query)) {
            throw new RuntimeException('Saving price history failed.');
        }
    });

    $csvPath = tempnam(sys_get_temp_dir(), 'products-');
    file_put_contents($csvPath, "title,store,original_price,current_price,unit_price,unit\nPiens,rimi.lv,,1.19,,\n");

    expect(fn () => $this->artisan('products:import', ['file' => $csvPath, '--force' => true])->run())
        ->toThrow(RuntimeException::class, 'Saving price history failed.');

    expect(Product::query()->where('title', 'Piens')->sole()->current_price)->toBe('1.39')
        ->and(Product::query()->where('title', 'Maize')->sole()->offerHasEnded())->toBeFalse();

    unlink($csvPath);
});

test('an empty product file is refused', function () {
    $csvPath = tempnam(sys_get_temp_dir(), 'products-');
    file_put_contents($csvPath, "title,store,original_price,current_price,unit_price,unit\n");

    expect(fn () => $this->artisan('products:import', ['file' => $csvPath])->run())
        ->toThrow(RuntimeException::class, 'The product file has no products');

    unlink($csvPath);
});

test('rows with broken prices or titles are skipped and broken optional fields are left empty', function () {
    $longTitle = str_repeat('A', 300);
    importCsv(implode("\n", [
        'Piens,rimi.lv,,0.99,0.99,€/l',
        'Negatīvs,rimi.lv,,-1.50,,',
        'Nulle,rimi.lv,,0,,',
        'Kļūda,rimi.lv,,1.2.3,,',
        'Milzīgs,rimi.lv,,123456789.00,,',
        "{$longTitle},rimi.lv,,1.00,,",
        'Dīvaina vienība,rimi.lv,,2.00,4.00,€/gab',
        'Bez cenas,rimi.lv,,,,',
    ]));

    expect(Product::query()->orderBy('title')->pluck('title')->all())->toBe(['Bez cenas', 'Dīvaina vienība', 'Piens'])
        ->and(Product::query()->where('title', 'Dīvaina vienība')->sole())
        ->unit->toBeNull()
        ->unit_price->toBeNull();
});

test('image addresses must be web links and may be long', function () {
    $longUrl = 'https://cdn.example.com/'.str_repeat('a', 900).'.jpg';
    $csvPath = tempnam(sys_get_temp_dir(), 'products-');
    file_put_contents($csvPath, implode("\n", [
        'title,store,original_price,current_price,unit_price,unit,image_url',
        "Garš,rimi.lv,,1.00,,,{$longUrl}",
        'Skripts,rimi.lv,,1.00,,,javascript:alert(1)',
    ]));

    $this->artisan('products:import', ['file' => $csvPath])->assertSuccessful();

    expect(Product::query()->where('title', 'Garš')->sole()->image_url)->toBe($longUrl)
        ->and(Product::query()->where('title', 'Skripts')->sole()->image_url)->toBeNull();

    unlink($csvPath);
});
