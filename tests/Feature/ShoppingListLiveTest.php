<?php

use App\Models\Product;
use App\Models\ShoppingList;
use App\Models\User;
use Livewire\Livewire;

function liveListSetup(string $role): array
{
    $owner = User::factory()->create(['name' => 'Anna']);
    $member = User::factory()->create(['name' => 'Jānis']);
    $list = $owner->shoppingLists()->create(['name' => 'Kopīgais saraksts']);
    $list->members()->attach($member->id, ['role' => $role]);
    $product = Product::query()->create(['title' => 'Fresh Milk', 'store' => 'etop.lv', 'current_price' => 1.50]);
    $list->products()->attach($product->id, ['quantity' => 2]);

    return [$owner, $member, $list, $product];
}

test('the list page polls for changes', function () {
    [$owner, , $list] = liveListSetup(ShoppingList::ROLE_EDITOR);

    $this->actingAs($owner)
        ->get(route('cart.show', $list))
        ->assertOk()
        ->assertSee('wire:poll.3s', false)
        ->assertSee('Fresh Milk');
});

test('editors can tick items off and everyone sees who bought them', function () {
    [$owner, $editor, $list, $product] = liveListSetup(ShoppingList::ROLE_EDITOR);

    $this->actingAs($editor);
    Livewire::test('shopping-list-items', ['list' => $list])
        ->call('toggle', $product->id)
        ->assertSee('nopirka Jānis')
        ->assertSee('Nopirkts 1 no 1');

    $item = $list->products()->first()->pivot;
    expect($item->checked_at)->not->toBeNull()
        ->and($item->checked_by)->toBe($editor->id);

    $this->actingAs($owner);
    Livewire::test('shopping-list-items', ['list' => $list])
        ->assertSee('nopirka Jānis')
        ->call('toggle', $product->id)
        ->assertSee('Nopirkts 0 no 1');

    expect($list->products()->first()->pivot->checked_at)->toBeNull();
});

test('editors can change quantities and remove items', function () {
    [, $editor, $list, $product] = liveListSetup(ShoppingList::ROLE_EDITOR);

    $this->actingAs($editor);
    $component = Livewire::test('shopping-list-items', ['list' => $list])
        ->call('increase', $product->id)
        ->assertSee('4,50 €');

    $component->call('decrease', $product->id)->assertSee('3,00 €');
    $component->call('remove', $product->id)->assertSee('Šis saraksts ir tukšs.');

    expect($list->products()->count())->toBe(0);
});

test('viewers see the list but cannot tick items off', function () {
    [, $viewer, $list, $product] = liveListSetup(ShoppingList::ROLE_VIEWER);

    $this->actingAs($viewer);
    Livewire::test('shopping-list-items', ['list' => $list])
        ->assertSee('Fresh Milk')
        ->assertDontSeeHtml('wire:click')
        ->call('toggle', $product->id)
        ->assertForbidden();

    expect($list->products()->first()->pivot->checked_at)->toBeNull();
});
