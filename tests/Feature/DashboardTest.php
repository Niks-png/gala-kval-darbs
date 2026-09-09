<?php

use App\Models\User;
use App\Models\Product;

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
