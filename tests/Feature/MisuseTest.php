<?php

// Attempts to misuse or break the app: odd input, other people's data, abuse of the mail account.

use App\Models\Product;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

function offer(array $attributes = []): Product
{
    return Product::query()->create([
        'title' => 'Piens', 'store' => 'rimi.lv', 'current_price' => 1.00, ...$attributes,
    ]);
}

// --- Weird input types -------------------------------------------------------------------------

test('array instead of text in product search does not crash', function (string $field) {
    $this->actingAs(User::factory()->create())
        ->get(route('products.search').'?'.$field.'[]=x')
        ->assertStatus(200);
})->with(['q', 'store', 'category']);

test('array instead of text on the dashboard does not crash', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard').'?q[]=x&store[]=y&category[x]=z')
        ->assertStatus(200);
});

test('array instead of text on price history does not crash', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('price-history').'?q[]=x&period[]=30')
        ->assertStatus(200);
});

test('array instead of text in admin user search does not crash', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get(route('admin.users').'?q[]=x')
        ->assertStatus(200);
});

test('array instead of text on the login form does not crash', function () {
    $this->post(route('login.store'), ['email' => ['a@b.c'], 'password' => 'x'])
        ->assertStatus(302);
});

test('array instead of text in a password reset link does not crash', function () {
    $this->get(route('password.reset', 'token').'?email[]=x')
        ->assertStatus(200);
});

test('array instead of text in the list name is rejected', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('cart.store'), ['name' => ['x']])
        ->assertSessionHasErrors('name');
});

test('nonsense quantities are rejected', function (mixed $quantity) {
    $user = User::factory()->create();
    $list = $user->shoppingLists()->create(['name' => 'L']);

    $this->actingAs($user)
        ->post(route('products.add-to-list', offer()), ['shopping_list_id' => $list->id, 'quantity' => $quantity])
        ->assertSessionHasErrors('quantity');

    expect($list->products()->count())->toBe(0);
})->with([0, -5, 100, 'abc', '1e3', 2.5, [[1]]]);

test('recipe matching survives junk ingredients', function (mixed $ingredients) {
    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('recipes.match'), ['ingredients' => $ingredients]);

    expect($response->status())->toBeIn([200, 422]);
})->with([
    'string' => 'milk',
    'nested' => [[['milk']]],
    'blank' => [[' ']],
    'wildcards' => [['%', '_', '\\']],
    'too long' => [[str_repeat('a', 101)]],
    'emoji' => [['🥛🥛🥛']],
]);

test('adding many products rejects junk ids', function (mixed $ids) {
    $this->actingAs(User::factory()->create())
        ->postJson(route('cart.items.store-many'), ['product_ids' => $ids])
        ->assertStatus(422);
})->with([
    'sql' => [['1 OR 1=1']],
    'nested' => [[[1]]],
    'missing product' => [[999999]],
    'string' => '1,2,3',
]);

test('a huge search string does not crash', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('products.search', ['q' => str_repeat('piens ', 2000)]))
        ->assertStatus(200);
});

// --- Other people's lists ----------------------------------------------------------------------

test('a stranger cannot see, copy or change someone else\'s list', function () {
    [$owner, , $list, $product] = sharedListSetup();
    $stranger = User::factory()->create();
    $this->actingAs($stranger);

    $this->get(route('cart.show', $list))->assertForbidden();
    $this->post(route('cart.copy', $list))->assertForbidden();
    $this->post(route('cart.lists.items.store', [$list, $product]))->assertForbidden();
    $this->post(route('cart.activate', $list))->assertForbidden();
    $this->post(route('products.add-to-list', $product), ['shopping_list_id' => $list->id, 'quantity' => 1])->assertForbidden();
    $this->patch(route('cart.update', $list), ['name' => 'hacked'])->assertForbidden();
    $this->delete(route('cart.destroy', $list))->assertForbidden();

    expect($list->fresh()->name)->toBe('Kopīgais saraksts')
        ->and($list->products()->count())->toBe(0);
});

test('a stranger cannot sneak into someone else\'s list through the session', function () {
    [, , $list, $product] = sharedListSetup();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->withSession(['active_shopping_list_id' => $list->id])
        ->post(route('cart.items.store', $product));

    expect($list->products()->count())->toBe(0);
});

test('a viewer cannot change the list', function () {
    [, $viewer, $list, $product] = sharedListSetup(ShoppingList::ROLE_VIEWER);
    $list->products()->attach($product->id, ['quantity' => 2]);
    $this->actingAs($viewer);

    $this->post(route('cart.lists.items.store', [$list, $product]))->assertForbidden();
    $this->post(route('cart.lists.items.decrease', [$list, $product]))->assertForbidden();
    $this->delete(route('cart.lists.items.destroy', [$list, $product]))->assertForbidden();
    $this->post(route('cart.complete', $list))->assertForbidden();
    $this->post(route('cart.invite.store', $list), ['role' => 'editor'])->assertForbidden();
    $this->post(route('cart.members.store', $list), ['email' => 'x@y.z', 'role' => 'editor'])->assertForbidden();

    expect($list->products()->first()->pivot->quantity)->toBe(2);
});

test('a viewer cannot change the list through the live component', function (string $method) {
    [, $viewer, $list, $product] = sharedListSetup(ShoppingList::ROLE_VIEWER);
    $list->products()->attach($product->id, ['quantity' => 2]);

    $this->actingAs($viewer);
    Livewire::test('shopping-list-items', ['list' => $list])
        ->call($method, $product->id)
        ->assertForbidden();

    $item = $list->products()->first()->pivot;
    expect($item->quantity)->toBe(2)->and($item->checked_at)->toBeNull();
})->with(['toggle', 'increase', 'decrease', 'remove']);

test('a stranger cannot use the live component on someone else\'s list', function () {
    [, , $list, $product] = sharedListSetup();
    $list->products()->attach($product->id, ['quantity' => 2]);

    $this->actingAs(User::factory()->create());
    Livewire::test('shopping-list-items', ['list' => $list])
        ->call('remove', $product->id)
        ->call('increase', $product->id)
        ->call('toggle', $product->id);

    $item = $list->products()->first()?->pivot;
    expect($item)->not->toBeNull()
        ->and($item->quantity)->toBe(2)
        ->and($item->checked_at)->toBeNull();
});

test('an editor cannot promote themself, kick the owner or manage members', function () {
    [$owner, $editor, $list] = sharedListSetup(ShoppingList::ROLE_EDITOR);
    $other = User::factory()->create();
    $list->members()->attach($other->id, ['role' => 'viewer']);
    $this->actingAs($editor);

    $this->patch(route('cart.members.update', [$list, $editor]), ['role' => 'editor'])->assertForbidden();
    $this->delete(route('cart.members.destroy', [$list, $other]))->assertForbidden();
    $this->delete(route('cart.members.destroy', [$list, $owner]))->assertForbidden();

    expect($list->fresh()->user_id)->toBe($owner->id)
        ->and($list->roleFor($other))->toBe('viewer');
});

test('the owner cannot hand out an "owner" role', function () {
    [$owner, $member, $list] = sharedListSetup(ShoppingList::ROLE_VIEWER);

    $this->actingAs($owner)
        ->patch(route('cart.members.update', [$list, $member]), ['role' => 'owner'])
        ->assertSessionHasErrors('role');

    $this->actingAs($owner)
        ->post(route('cart.invite.store', $list), ['role' => 'owner'])
        ->assertSessionHasErrors('role');
});

test('someone else cannot accept or reject your invitation', function () {
    $owner = User::factory()->create();
    $friend = User::factory()->create();
    $list = $owner->shoppingLists()->create(['name' => 'L']);
    $invitation = $list->invitations()->create(['user_id' => $friend->id, 'invited_by' => $owner->id, 'role' => 'editor']);

    $this->actingAs(User::factory()->create());
    $this->post(route('invitations.accept', $invitation))->assertForbidden();
    $this->delete(route('invitations.destroy', $invitation))->assertForbidden();

    expect($invitation->fresh())->not->toBeNull();
});

test('expired, regenerated and switched-off invite links do not work', function () {
    $owner = User::factory()->create();
    $list = $owner->shoppingLists()->create(['name' => 'L']);
    $joiner = User::factory()->create();

    $this->actingAs($owner)->post(route('cart.invite.store', $list), ['role' => 'editor']);
    $oldToken = $list->fresh()->invite_token;
    $this->actingAs($owner)->post(route('cart.invite.store', $list), ['role' => 'editor']);
    $this->actingAs($joiner)->post(route('cart.invite.join', $oldToken))->assertNotFound();

    $token = $list->fresh()->invite_token;
    $this->travel(ShoppingList::INVITE_LINK_DAYS + 1)->days();
    $this->actingAs($joiner)->post(route('cart.invite.join', $token))->assertNotFound();

    expect($list->roleFor($joiner))->toBeNull();
});

test('a finished list cannot be changed afterwards', function () {
    [$owner, , $list, $product] = sharedListSetup();
    $list->products()->attach($product->id, ['quantity' => 1, 'checked_at' => now()]);
    $this->actingAs($owner)->post(route('cart.complete', $list));
    $total = $list->fresh()->completed_total;

    $this->post(route('cart.lists.items.store', [$list, $product]));
    $this->delete(route('cart.lists.items.destroy', [$list, $product]));
    $this->post(route('products.add-to-list', $product), ['shopping_list_id' => $list->id, 'quantity' => 5]);

    expect($list->products()->first()->pivot->quantity)->toBe(1)
        ->and($list->fresh()->completed_total)->toBe($total);
});

test('quantity cannot go above the limit', function () {
    $user = User::factory()->create();
    $list = $user->shoppingLists()->create(['name' => 'L']);
    $product = offer();

    $this->actingAs($user);
    for ($i = 0; $i < 3; $i++) {
        $this->post(route('products.add-to-list', $product), ['shopping_list_id' => $list->id, 'quantity' => 99]);
    }

    expect($list->products()->first()->pivot->quantity)->toBe(ShoppingList::MAX_QUANTITY);
});

test('products that are no longer on offer cannot be added', function (string $how) {
    $user = User::factory()->create();
    $list = $user->shoppingLists()->create(['name' => 'L']);
    $gone = offer(['offer_ended_at' => now()->subDay()]);

    $this->actingAs($user)->withSession(['active_shopping_list_id' => $list->id]);
    match ($how) {
        'quick add' => $this->post(route('cart.items.store', $gone)),
        'product page' => $this->post(route('products.add-to-list', $gone), ['shopping_list_id' => $list->id, 'quantity' => 1]),
        'list page' => $this->post(route('cart.lists.items.store', [$list, $gone])),
    };

    expect($list->products()->count())->toBe(0);
})->with(['quick add', 'product page', 'list page']);

test('the product page of an ended offer has no add-to-list form', function () {
    $user = User::factory()->create();
    $user->shoppingLists()->create(['name' => 'L']);
    $gone = offer(['offer_ended_at' => now()->subDay()]);

    $this->actingAs($user)->get(route('products.show', $gone))
        ->assertOk()
        ->assertSee('Piedāvājums ir beidzies, tāpēc to nevar pievienot sarakstam.')
        ->assertDontSee(route('products.add-to-list', $gone));
});

test('more of an ended offer already on the list can still be added', function () {
    $user = User::factory()->create();
    $list = $user->shoppingLists()->create(['name' => 'L']);
    $gone = offer(['offer_ended_at' => now()->subDay()]);
    $list->products()->attach($gone->id, ['quantity' => 1]);

    $this->actingAs($user)->post(route('cart.lists.items.store', [$list, $gone]));

    expect($list->products()->first()->pivot->quantity)->toBe(2);
});

// --- Notifications, admin, accounts -----------------------------------------------------------

test('you cannot delete someone else\'s notification', function () {
    $victim = User::factory()->create();
    $victim->notifications()->create([
        'id' => (string) Str::uuid(), 'type' => 'x', 'data' => ['a' => 1],
    ]);
    $id = $victim->notifications()->first()->id;

    $this->actingAs(User::factory()->create())->delete(route('notifications.destroy', $id));

    expect($victim->notifications()->count())->toBe(1);
});

test('normal users cannot reach the admin panel', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('admin.index'))->assertForbidden();
    $this->post(route('admin.scrape'))->assertForbidden();
    $this->patch(route('admin.users.update', $user), ['is_admin' => true])->assertForbidden();

    expect($user->fresh()->is_admin)->toBeFalse();
});

test('unverified users cannot use the app', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('list names with HTML are shown as text, not run', function () {
    $user = User::factory()->create();
    $user->shoppingLists()->create(['name' => '<script>alert(1)</script>']);

    $this->actingAs($user)->get(route('cart'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

test('changing the profile email cannot be used to spam other people', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'me@example.com']);
    $this->actingAs($user);

    for ($i = 0; $i < 5; $i++) {
        Livewire::test('pages::settings.profile')
            ->set('email', "victim{$i}@example.com")
            ->call('updateProfileInformation')
            ->assertHasNoErrors();
    }

    Livewire::test('pages::settings.profile')
        ->set('email', 'victim-too-many@example.com')
        ->call('updateProfileInformation')
        ->assertHasErrors('email');

    expect(Notification::sent($user, VerifyEmail::class)->count())->toBe(5)
        ->and($user->fresh()->email)->toBe('victim4@example.com');
});

test('changing only the name is not limited', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    for ($i = 0; $i < 10; $i++) {
        Livewire::test('pages::settings.profile')
            ->set('name', "Vārds {$i}")
            ->call('updateProfileInformation')
            ->assertHasNoErrors();
    }

    expect($user->fresh()->name)->toBe('Vārds 9');
});

test('login is rate limited', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
});

test('the same email with different capitals cannot make a second account', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    $this->post(route('register.store'), [
        'name' => 'Fake Anna', 'email' => 'ANNA@example.com',
        'password' => 'Parole123!x', 'password_confirmation' => 'Parole123!x',
    ]);

    expect(User::query()->whereRaw('lower(email) = ?', ['anna@example.com'])->count())->toBe(1);
});

test('deleting an account keeps shared lists for the other members', function () {
    [$owner, $editor, $list, $product] = sharedListSetup();
    $list->products()->attach($product->id, ['quantity' => 3]);

    $owner->delete();

    expect($list->fresh()->user_id)->toBe($editor->id)
        ->and($list->products()->count())->toBe(1);
});
