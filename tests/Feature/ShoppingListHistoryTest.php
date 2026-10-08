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

test('finishing a list counts only ticked items', function () {
    $user = User::factory()->create();
    [$list, $milk] = historyList($user);
    $list->toggleProductChecked($milk->id, $user);

    $this->actingAs($user)->post(route('cart.complete', $list))->assertRedirect(route('cart.show', $list));

    expect($list->refresh()->isCompleted())->toBeTrue()
        ->and($list->completed_total)->toBe('3.00');
});

test('a list with nothing ticked cannot be finished', function () {
    $user = User::factory()->create();
    [$list] = historyList($user);

    $this->actingAs($user)
        ->post(route('cart.complete', $list))
        ->assertRedirect(route('cart.show', $list))
        ->assertSessionHas('error', 'Atzīmē nopirktās preces, pirms pabeidz iepirkšanos.');

    expect($list->refresh()->isCompleted())->toBeFalse();
});

test('a finished list keeps the prices of the day it was finished', function () {
    $user = User::factory()->create();
    [$list, $milk, $bread] = historyList($user);
    $list->toggleProductChecked($milk->id, $user);
    $list->complete();

    // The scraper changes the price the next day.
    $milk->update(['current_price' => 2.99]);

    $item = $list->products()->whereKey($milk->id)->sole();
    expect(ShoppingList::itemPrice($item))->toBe(1.50)
        ->and($list->refresh()->completed_total)->toBe('3.00');

    $this->actingAs($user)->get(route('cart.show', $list))
        ->assertSee('3,00 €')       // 2 × 1,50 € saved when finished
        ->assertDontSee('5,98 €')   // not 2 × today's 2,99 €
        ->assertSee('nav nopirkts'); // bread was not ticked
});

test('finished lists cannot be changed, reopened or finished again', function () {
    $user = User::factory()->create();
    [$list, $milk, $bread] = historyList($user);
    $list->toggleProductChecked($milk->id, $user);
    $list->complete();

    $this->actingAs($user);
    $this->post(route('cart.lists.items.store', [$list, $milk]))->assertForbidden();
    $this->post(route('cart.lists.items.decrease', [$list, $milk]))->assertForbidden();
    $this->post(route('cart.complete', $list))->assertRedirect(route('cart.show', $list));

    // Even calls that skip the policy check change nothing once the list is finished.
    expect($list->addProduct($bread->id))->toBeFalse()
        ->and($list->decreaseProduct($milk->id))->toBeFalse()
        ->and($list->removeProduct($milk->id))->toBeFalse()
        ->and($list->toggleProductChecked($milk->id, $user))->toBeFalse()
        ->and($list->complete())->toBeFalse()
        ->and($list->products()->whereKey($milk->id)->sole()->pivot->quantity)->toBe(2)
        ->and($list->refresh()->completed_total)->toBe('3.00');
});

test('"buy again" makes a new list with the same items and leaves the finished one alone', function () {
    $user = User::factory()->create();
    [$list, $milk] = historyList($user);
    $list->toggleProductChecked($milk->id, $user);
    $list->complete();

    $response = $this->actingAs($user)->post(route('cart.copy', $list));

    $copy = ShoppingList::query()->latest('id')->first();
    $response->assertRedirect(route('cart.show', $copy));

    expect($copy->id)->not->toBe($list->id)
        ->and($copy->isCompleted())->toBeFalse()
        ->and($copy->products()->pluck('quantity', 'products.id')->all())->toBe($list->products()->pluck('quantity', 'products.id')->all())
        ->and($copy->products()->wherePivotNotNull('checked_at')->count())->toBe(0)
        ->and($list->refresh()->isCompleted())->toBeTrue();
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
    [$list, $milk, $bread] = historyList($user);
    $list->toggleProductChecked($milk->id, $user);
    $list->toggleProductChecked($bread->id, $user);
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
