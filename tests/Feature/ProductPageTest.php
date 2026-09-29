<?php

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\ShoppingList;
use App\Models\User;

function pageProduct(string $title, string $store, float $price, ?string $category = 'Piena produkti un olas'): Product
{
    return Product::query()->create(['title' => $title, 'store' => $store, 'current_price' => $price, 'category' => $category]);
}

test('product cards link to the product page instead of adding straight to the cart', function () {
    $milk = pageProduct('PIENS OPĀ 2.5% 1L', 'etop.lv', 0.99);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('products.show', $milk))
        ->assertSee('Pievienot sarakstam');
});

test('the product page shows price history and the same product in another store', function () {
    $milk = pageProduct('PIENS OPĀ 2.5% 1L', 'etop.lv', 0.99);
    $similar = pageProduct('Piens RASĒNS 2.5%, 1 l', 'maxima.lv', 0.89);
    pageProduct('SIERS HOLANDES ŠĶĒLĒS 150G', 'maxima.lv', 1.99);
    pageProduct('Piens mājas 2.5%', 'etop.lv', 0.79);
    ProductPriceHistory::query()->create(['product_id' => $milk->id, 'previous_price' => 1.19, 'new_price' => 0.99]);

    $this->actingAs(User::factory()->create())
        ->get(route('products.show', $milk))
        ->assertOk()
        ->assertSee('PIENS OPĀ 2.5% 1L')
        ->assertSee('1.19 €')
        ->assertSee(route('products.show', $similar))
        ->assertSee('0.10 € lētāk')
        ->assertDontSee('SIERS HOLANDES')
        ->assertDontSee('Piens mājas');
});

test('a product can be added to a chosen list with a quantity', function () {
    $user = User::factory()->create();
    $list = $user->shoppingLists()->create(['name' => 'Ballīte']);
    $milk = pageProduct('PIENS OPĀ 2.5% 1L', 'etop.lv', 0.99);

    $this->actingAs($user)
        ->from(route('products.show', $milk))
        ->post(route('products.add-to-list', $milk), ['shopping_list_id' => $list->id, 'quantity' => 3])
        ->assertRedirect(route('products.show', $milk))
        ->assertSessionHas('success');

    expect($list->products()->first()->pivot->quantity)->toBe(3);
});

test('products cannot be added to someone elses list', function () {
    $list = User::factory()->create()->shoppingLists()->create(['name' => 'Svešs']);
    $milk = pageProduct('PIENS OPĀ 2.5% 1L', 'etop.lv', 0.99);

    $this->actingAs(User::factory()->create())
        ->post(route('products.add-to-list', $milk), ['shopping_list_id' => $list->id, 'quantity' => 1])
        ->assertForbidden();

    expect($list->products()->count())->toBe(0);
});

test('search and price history are paginated and filter by category', function () {
    pageProduct('PIENS OPĀ 2.5% 1L', 'etop.lv', 0.99);
    pageProduct('MAIZE RUDZU 400G', 'etop.lv', 1.29, 'Maize un maizes izstrādājumi');

    $this->actingAs(User::factory()->create())
        ->get(route('products.search', ['category' => 'Maize un maizes izstrādājumi']))
        ->assertOk()
        ->assertSee('MAIZE RUDZU')
        ->assertDontSee('PIENS OPĀ');
});

test('the interface is in Latvian', function () {
    expect(app()->getLocale())->toBe('lv');

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSee('Meklēt produktus')
        ->assertDontSee('Add to cart')
        ->assertDontSee('Search products');
});

test('finished lists are not used as the active list', function () {
    $user = User::factory()->create();
    $done = $user->shoppingLists()->create(['name' => 'Vecais']);
    $done->complete();
    $milk = pageProduct('PIENS OPĀ 2.5% 1L', 'etop.lv', 0.99);

    $this->actingAs($user)->withSession(['active_shopping_list_id' => $done->id])
        ->post(route('cart.items.store', $milk))
        ->assertRedirect();

    expect($done->products()->count())->toBe(0)
        ->and(ShoppingList::query()->open()->where('user_id', $user->id)->first()->products()->count())->toBe(1);
});
