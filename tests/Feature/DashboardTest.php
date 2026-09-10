<?php

use App\Models\User;
use App\Models\Product;
use App\Models\ProductPriceHistory;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('authenticated users can visit the cart', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('cart'));

    $response->assertOk();
});

test('authenticated users can view price history', function () {
    $user = User::factory()->create();
    $product = Product::query()->create([
        'title' => 'Fresh Milk',
        'store' => 'etop.lv',
        'current_price' => 1.49,
    ]);
    ProductPriceHistory::query()->create([
        'product_id' => $product->id,
        'previous_price' => 1.99,
        'new_price' => 1.49,
    ]);

    $response = $this->actingAs($user)->get(route('price-history'));

    $response->assertOk()
        ->assertSee('Fresh Milk')
        ->assertSee('1.99')
        ->assertSee('1.49');
});

test('authenticated users can visit the map', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get(route('map'));

    $response->assertOk()
        ->assertSee('Karte');
});

test('authenticated users can search products by name', function () {
    $user = User::factory()->create();
    Product::query()->create([
        'title' => 'Fresh Milk',
        'store' => 'etop.lv',
        'current_price' => 1.99,
    ]);
    Product::query()->create([
        'title' => 'Bread',
        'store' => 'maxima.lv',
        'current_price' => 0.99,
    ]);

    $response = $this->actingAs($user)->get(route('products.search', ['q' => 'milk']));

    $response->assertOk()
        ->assertSee('Fresh Milk')
        ->assertDontSee('Bread');
});

test('authenticated users can add a product to the cart', function () {
    $user = User::factory()->create();
    $product = Product::query()->create([
        'title' => 'Fresh Milk',
        'store' => 'etop.lv',
        'current_price' => 1.99,
    ]);

    $this->actingAs($user)
        ->post(route('cart.items.store', $product))
        ->assertRedirect();

    $this->withHeader('Accept', 'application/json')
        ->post(route('cart.items.store', $product))
        ->assertOk()
        ->assertJson(['message' => 'Produkts veiksmīgi pievienots iepirkuma sarakstam']);

    $this->get(route('cart'))
        ->assertOk()
        ->assertSee('Fresh Milk')
        ->assertSee('Quantity: 2');
});

test('authenticated users can change and remove cart quantities', function () {
    $user = User::factory()->create();
    $product = Product::query()->create([
        'title' => 'Fresh Milk',
        'store' => 'etop.lv',
        'current_price' => 1.99,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['cart' => [$product->id => 2]])
        ->post(route('cart.items.decrease', $product));

    $response->assertRedirect(route('cart'));
    expect(session('cart'))->toBe([$product->id => 1]);

    $response = $this->delete(route('cart.items.destroy', $product));

    $response->assertRedirect(route('cart'));
    expect(session('cart'))->toBe([]);
});

test('product imports record changed previous prices', function () {
    $product = Product::query()->create([
        'title' => 'Fresh Milk',
        'store' => 'etop.lv',
        'current_price' => 1.99,
        'price' => 1.99,
    ]);
    $csvPath = tempnam(sys_get_temp_dir(), 'products-');
    file_put_contents($csvPath, implode("\n", [
        'title,store,original_price,current_price,unit_price,unit',
        'Fresh Milk,etop.lv,2.49,1.49,1.49,€/l',
    ]));

    $this->artisan('products:import', ['file' => $csvPath])->assertSuccessful();

    expect(ProductPriceHistory::query()->where('product_id', $product->id)->first())
        ->previous_price->toBe('1.99')
        ->new_price->toBe('1.49');

    unlink($csvPath);
});
