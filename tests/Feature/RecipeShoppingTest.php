<?php

use App\Models\Product;
use App\Models\User;
use App\Services\IngredientProductMatcher;

function recipeProduct(string $title, float $price): Product
{
    return Product::query()->create(['title' => $title, 'store' => 'etop.lv', 'current_price' => $price]);
}

test('ingredients are matched to the cheapest real product, not a flavoured one', function () {
    recipeProduct('NŪDELES REEVA AR VISTAS GARŠU 60G', 0.44);
    recipeProduct('BARĪBA KAĶIEM VISTA 85G', 0.49);
    $chicken = recipeProduct('VISTAS FILEJA 1KG', 5.99);
    recipeProduct('PIENS OPĀ 2.5% 1L', 0.99);
    $milk = recipeProduct('PIENS TIP TOP 2% 1L', 0.79);
    recipeProduct('ŠOKOLĀDE AR PIENU', 0.50);

    $matcher = new IngredientProductMatcher;

    expect($matcher->match('Chicken Breast')?->is($chicken))->toBeTrue()
        ->and($matcher->match('Milk')?->is($milk))->toBeTrue()
        ->and($matcher->match('Water'))->toBeNull()
        ->and($matcher->match('Dragonfruit'))->toBeNull();
});

test('the match endpoint returns products for each ingredient', function () {
    $eggs = recipeProduct('OLAS TIP TOP 10GAB.', 2.39);

    $this->actingAs(User::factory()->create())
        ->postJson(route('recipes.match'), ['ingredients' => ['Eggs', 'Saffron']])
        ->assertOk()
        ->assertJsonPath('matches.0.ingredient', 'Eggs')
        ->assertJsonPath('matches.0.product.id', $eggs->id)
        ->assertJsonPath('matches.1.product', null);
});

test('recipe products can be added to the active list in one request', function () {
    $user = User::factory()->create();
    $eggs = recipeProduct('OLAS TIP TOP 10GAB.', 2.39);
    $milk = recipeProduct('PIENS TIP TOP 2% 1L', 0.79);
    $list = $user->shoppingLists()->create(['name' => 'Vakariņas']);
    $list->products()->attach($milk->id, ['quantity' => 1]);

    $this->actingAs($user)
        ->postJson(route('cart.items.store-many'), ['product_ids' => [$eggs->id, $milk->id]])
        ->assertOk()
        ->assertJsonPath('url', route('cart.show', $list));

    expect($list->products()->pluck('quantity', 'product_id')->all())
        ->toEqual([$milk->id => 2, $eggs->id => 1]);
});

test('adding recipe products validates the ids', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('cart.items.store-many'), ['product_ids' => [999]])
        ->assertUnprocessable();
});
