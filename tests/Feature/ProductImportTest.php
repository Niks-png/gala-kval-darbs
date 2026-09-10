<?php

use App\Models\Product;

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
