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

test('legacy scraped products use the supplied store', function () {
    $csvPath = tempnam(sys_get_temp_dir(), 'products-');

    file_put_contents($csvPath, implode("\n", [
        'title,original_price,current_price',
        'Bread,1.50,0.99',
    ]));

    $this->artisan('products:import', ['file' => $csvPath, '--store' => 'etop.lv'])
        ->assertSuccessful();

    expect(Product::query()->first()->store)->toBe('etop.lv');

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
        'price' => 0.99,
    ]);
    Product::query()->create([
        'title' => 'Bread',
        'store' => 'maxima.lv',
        'current_price' => 1.49,
        'price' => 1.49,
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
