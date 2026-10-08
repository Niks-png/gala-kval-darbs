<?php

use App\Models\Product;
use App\Models\ShoppingList;
use App\Models\User;

test('adding the same product from two places adds both, from separately loaded copies of the list', function () {
    [$owner, $editor, $list, $milk] = sharedListSetup();

    // Two editors, each holding the list as it was when their page loaded.
    $ownersCopy = ShoppingList::query()->find($list->id);
    $editorsCopy = ShoppingList::query()->find($list->id);

    $ownersCopy->addProduct($milk->id);
    $editorsCopy->addProduct($milk->id);
    $ownersCopy->addProduct($milk->id, 2);

    expect($list->products()->whereKey($milk->id)->sole()->pivot->quantity)->toBe(4);
});

test('quantities never go above the limit, however products are added', function () {
    [$owner, , $list, $milk] = sharedListSetup();

    $list->addProduct($milk->id, 98);
    $list->addProduct($milk->id);
    $list->addProduct($milk->id, 5);

    expect($list->products()->whereKey($milk->id)->sole()->pivot->quantity)->toBe(ShoppingList::MAX_QUANTITY);

    $this->actingAs($owner)
        ->post(route('products.add-to-list', $milk), ['shopping_list_id' => $list->id, 'quantity' => 150])
        ->assertSessionHasErrors('quantity');
});

test('decreasing to zero removes the item, and decreasing a missing item does nothing', function () {
    [$owner, , $list, $milk] = sharedListSetup();
    $list->addProduct($milk->id, 2);

    $list->decreaseProduct($milk->id);
    expect($list->products()->whereKey($milk->id)->sole()->pivot->quantity)->toBe(1);

    $list->decreaseProduct($milk->id);
    $list->decreaseProduct($milk->id);
    expect($list->products()->count())->toBe(0);
});

test('opening the lists page does not create a list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('cart'))->assertOk();
    $this->get(route('cart'))->assertOk();

    expect(ShoppingList::query()->count())->toBe(0);
});

test('a product is added to a shared list the user can edit before a new list is made for them', function () {
    [$owner, $editor, $list, $milk] = sharedListSetup(ShoppingList::ROLE_EDITOR);

    $this->actingAs($editor)->post(route('cart.items.store', $milk))->assertRedirect();

    expect($list->products()->whereKey($milk->id)->exists())->toBeTrue()
        ->and(ShoppingList::query()->count())->toBe(1);
});

test('a viewer gets their own list, not the shared one they cannot edit', function () {
    [$owner, $viewer, $list, $milk] = sharedListSetup(ShoppingList::ROLE_VIEWER);

    $this->actingAs($viewer)->post(route('cart.items.store', $milk))->assertRedirect();

    expect($list->products()->count())->toBe(0)
        ->and($viewer->shoppingLists()->sole()->products()->whereKey($milk->id)->exists())->toBeTrue();
});

test('recipe products are added all together or not at all, and only current offers', function () {
    $user = User::factory()->create();
    $milk = Product::query()->create(['title' => 'Piens', 'store' => 'rimi.lv', 'current_price' => 0.99]);
    $ended = Product::query()->create(['title' => 'Vecā maize', 'store' => 'rimi.lv', 'current_price' => 1.29, 'offer_ended_at' => now()]);

    $this->actingAs($user)
        ->postJson(route('cart.items.store-many'), ['product_ids' => [$milk->id, $ended->id]])
        ->assertJsonValidationErrors('product_ids.1');

    expect(ShoppingList::query()->count())->toBe(0);

    $this->postJson(route('cart.items.store-many'), ['product_ids' => [$milk->id]])->assertOk();

    expect($user->shoppingLists()->sole()->products()->pluck('products.id')->all())->toBe([$milk->id]);
});

test('deleting an owner hands shared lists to an editor and deletes lists nobody else uses', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $editor = User::factory()->create();
    $shared = $owner->shoppingLists()->create(['name' => 'Ģimenes saraksts']);
    $shared->members()->attach($viewer->id, ['role' => ShoppingList::ROLE_VIEWER]);
    $shared->members()->attach($editor->id, ['role' => ShoppingList::ROLE_EDITOR]);
    $private = $owner->shoppingLists()->create(['name' => 'Mans']);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.users.destroy', $owner))
        ->assertRedirect();

    $shared->refresh();
    expect($shared->user_id)->toBe($editor->id)
        ->and($shared->roleFor($editor))->toBe('owner')
        ->and($shared->roleFor($viewer))->toBe(ShoppingList::ROLE_VIEWER)
        ->and(ShoppingList::query()->find($private->id))->toBeNull();
});

test('a shared list with only viewers goes to the longest-standing viewer', function () {
    [$owner, $viewer, $list] = sharedListSetup(ShoppingList::ROLE_VIEWER);

    $owner->delete();

    expect($list->refresh()->user_id)->toBe($viewer->id)
        ->and($list->members()->count())->toBe(0);
});
