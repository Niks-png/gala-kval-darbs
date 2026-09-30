<?php

use App\Models\Product;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Support\Carbon;

function historyList(User $user): array
{
    $list = $user->shoppingLists()->create(['name' => 'Nedēļas iepirkumi']);
    $milk = Product::query()->create(['title' => 'Milk', 'store' => 'etop.lv', 'current_price' => 1.50]);
    $bread = Product::query()->create(['title' => 'Bread', 'store' => 'etop.lv', 'current_price' => 2.00]);
    $list->products()->attach($milk->id, ['quantity' => 2]);
    $list->products()->attach($bread->id, ['quantity' => 1]);

    return [$list, $milk, $bread];
}

test('finishing a list saves the total of the whole list when nothing is ticked', function () {
    $user = User::factory()->create();
    [$list] = historyList($user);

    $this->actingAs($user)
        ->post(route('cart.complete', $list))
        ->assertRedirect(route('cart.show', $list));

    $list->refresh();
    expect($list->isCompleted())->toBeTrue()
        ->and($list->completed_total)->toBe('5.00');
});

test('finishing a list counts only ticked items when some are ticked', function () {
    $user = User::factory()->create();
    [$list, $milk] = historyList($user);
    $list->toggleProductChecked($milk->id, $user);

    $this->actingAs($user)->post(route('cart.complete', $list));

    expect($list->refresh()->completed_total)->toBe('3.00');
});

test('finished lists are read-only until reopened', function () {
    $user = User::factory()->create();
    [$list, $milk] = historyList($user);
    $list->complete();

    $this->actingAs($user)
        ->post(route('cart.lists.items.store', [$list, $milk]))
        ->assertForbidden();

    $this->delete(route('cart.reopen', $list))->assertRedirect(route('cart.show', $list));

    expect($list->refresh()->isCompleted())->toBeFalse();

    $this->post(route('cart.lists.items.store', [$list, $milk]))->assertRedirect();
});

test('viewers cannot finish a shared list', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    [$list] = historyList($owner);
    $list->members()->attach($viewer->id, ['role' => ShoppingList::ROLE_VIEWER]);

    $this->actingAs($viewer)->post(route('cart.complete', $list))->assertForbidden();

    expect($list->refresh()->isCompleted())->toBeFalse();
});

test('the lists page shows finished lists and monthly spending', function () {
    Carbon::setTestNow('2026-09-15 12:00:00');
    $user = User::factory()->create();
    [$list] = historyList($user);
    $list->complete();

    $old = $user->shoppingLists()->create(['name' => 'Augusta iepirkumi']);
    $old->forceFill(['completed_at' => '2026-08-10 10:00:00', 'completed_total' => 12.40])->save();

    $this->actingAs($user)
        ->get(route('cart'))
        ->assertOk()
        ->assertSee('Iepirkumu vēsture')
        ->assertSeeInOrder(['Iztērēts šomēnes', '5,00 €'])
        ->assertSee('Pēdējos 6 mēnešos kopā: 17,40 €')
        ->assertSeeInOrder(['Nedēļas iepirkumi', 'Augusta iepirkumi']);

    Carbon::setTestNow();
});
