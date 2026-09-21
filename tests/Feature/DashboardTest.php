<?php

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\User;

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

test('price history does not flag an unchanged snapshot as a price change', function () {
    $user = User::factory()->create();
    $product = Product::query()->create([
        'title' => 'Fresh Milk',
        'store' => 'etop.lv',
        'current_price' => 1.49,
    ]);
    ProductPriceHistory::query()->create([
        'product_id' => $product->id,
        'previous_price' => 1.49,
        'new_price' => 1.49,
    ]);

    $this->actingAs($user)
        ->get(route('price-history'))
        ->assertOk()
        ->assertSee('No change yet');
});

test('price history filters by search query and store', function () {
    $user = User::factory()->create();
    $milk = Product::query()->create(['title' => 'Fresh Milk', 'store' => 'maxima.lv', 'current_price' => 1.49]);
    $bread = Product::query()->create(['title' => 'Bread', 'store' => 'etop.lv', 'current_price' => 0.99]);
    ProductPriceHistory::query()->create(['product_id' => $milk->id, 'previous_price' => 1.99, 'new_price' => 1.49]);
    ProductPriceHistory::query()->create(['product_id' => $bread->id, 'previous_price' => 1.09, 'new_price' => 0.99]);

    $this->actingAs($user)
        ->get(route('price-history', ['q' => 'milk']))
        ->assertOk()
        ->assertSee('Fresh Milk')
        ->assertDontSee('Bread');

    $this->get(route('price-history', ['store' => 'etop.lv']))
        ->assertOk()
        ->assertSee('Bread')
        ->assertDontSee('Fresh Milk');
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

test('authenticated users can add a product to their active shopping list', function () {
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

    $list = $user->shoppingLists()->firstOrFail();

    $this->get(route('cart.show', $list))
        ->assertOk()
        ->assertSee('Fresh Milk');

    expect($list->products()->first()->pivot->quantity)->toBe(2);
});

test('authenticated users can create multiple named shopping lists', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('cart.store'), ['name' => 'Nedēļas iepirkumi'])
        ->assertRedirect();

    $this->post(route('cart.store'), ['name' => 'Ballītes saraksts'])
        ->assertRedirect();

    expect($user->shoppingLists()->pluck('name')->all())
        ->toBe(['Nedēļas iepirkumi', 'Ballītes saraksts']);

    $this->get(route('cart'))
        ->assertOk()
        ->assertSee('Nedēļas iepirkumi')
        ->assertSee('Ballītes saraksts');
});

test('authenticated users can change and remove shopping list item quantities', function () {
    $user = User::factory()->create();
    $product = Product::query()->create([
        'title' => 'Fresh Milk',
        'store' => 'etop.lv',
        'current_price' => 1.99,
    ]);
    $list = $user->shoppingLists()->create(['name' => 'Mans saraksts']);
    $list->products()->attach($product->id, ['quantity' => 2]);

    $this->actingAs($user)
        ->post(route('cart.lists.items.decrease', [$list, $product]))
        ->assertRedirect(route('cart.show', $list));

    expect($list->products()->first()->pivot->quantity)->toBe(1);

    $this->delete(route('cart.lists.items.destroy', [$list, $product]))
        ->assertRedirect(route('cart.show', $list));

    expect($list->products()->count())->toBe(0);
});

test('users cannot view or modify another users shopping list', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $list = $owner->shoppingLists()->create(['name' => 'Mans saraksts']);

    $this->actingAs($intruder)
        ->get(route('cart.show', $list))
        ->assertForbidden();
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
