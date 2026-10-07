<?php

use App\Models\Product;
use App\Models\ProductPriceHistory;

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
