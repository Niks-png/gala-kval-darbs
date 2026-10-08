<?php

use App\Models\ShoppingList;
use App\Models\User;

test('inviting by email sends an invitation the user can accept from notifications', function () {
    $owner = User::factory()->create(['name' => 'Anna']);
    $friend = User::factory()->create(['email' => 'friend@example.com']);
    $list = $owner->shoppingLists()->create(['name' => 'Kopīgais saraksts']);

    $this->actingAs($owner)
        ->post(route('cart.members.store', $list), ['email' => 'friend@example.com', 'role' => 'editor'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($list->roleFor($friend))->toBeNull();

    $invitation = $friend->shoppingListInvitations()->firstOrFail();

    $this->actingAs($friend)
        ->get(route('notifications'))
        ->assertOk()
        ->assertSee('Anna')
        ->assertSee('Kopīgais saraksts')
        ->assertSee('Pieņemt');

    $this->get(route('cart.show', $list))->assertForbidden();

    $this->post(route('invitations.accept', $invitation))
        ->assertRedirect(route('cart.show', $list));

    expect($list->roleFor($friend))->toBe('editor')
        ->and($friend->shoppingListInvitations()->count())->toBe(0);

    $this->get(route('cart'))
        ->assertOk()
        ->assertSee('Kopīgoti ar mani')
        ->assertSee('Kopīgais saraksts');
});

test('invited users can reject an invitation', function () {
    $owner = User::factory()->create();
    $friend = User::factory()->create();
    $list = $owner->shoppingLists()->create(['name' => 'Kopīgais saraksts']);
    $invitation = $list->invitations()->create(['user_id' => $friend->id, 'invited_by' => $owner->id, 'role' => 'editor']);

    $this->actingAs($friend)
        ->delete(route('invitations.destroy', $invitation))
        ->assertRedirect();

    expect($list->roleFor($friend))->toBeNull()
        ->and($list->invitations()->count())->toBe(0);
});

test('owner can cancel a pending invitation and others cannot touch it', function () {
    $owner = User::factory()->create();
    $friend = User::factory()->create();
    $stranger = User::factory()->create();
    $list = $owner->shoppingLists()->create(['name' => 'Kopīgais saraksts']);
    $invitation = $list->invitations()->create(['user_id' => $friend->id, 'invited_by' => $owner->id, 'role' => 'editor']);

    $this->actingAs($stranger)->post(route('invitations.accept', $invitation))->assertForbidden();
    $this->delete(route('invitations.destroy', $invitation))->assertForbidden();
    $this->actingAs($owner)->post(route('invitations.accept', $invitation))->assertForbidden();

    $this->get(route('cart.show', $list))->assertSee('Gaida atbildi');

    $this->delete(route('invitations.destroy', $invitation))->assertRedirect();

    expect($list->invitations()->count())->toBe(0);
});

test('inviting an unknown email, an existing member or a pending invitee fails validation', function () {
    [$owner, $member, $list] = sharedListSetup();
    $pending = User::factory()->create();
    $list->invitations()->create(['user_id' => $pending->id, 'invited_by' => $owner->id, 'role' => 'editor']);

    $this->actingAs($owner)
        ->post(route('cart.members.store', $list), ['email' => 'nobody@example.com', 'role' => 'editor'])
        ->assertSessionHasErrors('email');

    $this->post(route('cart.members.store', $list), ['email' => $member->email, 'role' => 'viewer'])
        ->assertSessionHasErrors('email');

    $this->post(route('cart.members.store', $list), ['email' => $owner->email, 'role' => 'viewer'])
        ->assertSessionHasErrors('email');

    $this->post(route('cart.members.store', $list), ['email' => $pending->email, 'role' => 'viewer'])
        ->assertSessionHasErrors('email');
});

test('the notification bell shows the number of pending invitations', function () {
    $owner = User::factory()->create();
    $friend = User::factory()->create();
    $list = $owner->shoppingLists()->create(['name' => 'Kopīgais saraksts']);
    $list->invitations()->create(['user_id' => $friend->id, 'invited_by' => $owner->id, 'role' => 'editor']);

    $this->actingAs($friend)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('notifications'))
        ->assertSeeInOrder(['Paziņojumi', '1']);
});

test('editors can add and remove items', function () {
    [, $editor, $list, $product] = sharedListSetup(ShoppingList::ROLE_EDITOR);

    $this->actingAs($editor)
        ->post(route('cart.lists.items.store', [$list, $product]))
        ->assertRedirect(route('cart.show', $list));

    expect($list->products()->count())->toBe(1);

    $this->delete(route('cart.lists.items.destroy', [$list, $product]))
        ->assertRedirect(route('cart.show', $list));

    expect($list->products()->count())->toBe(0);
});

test('editors can quick add to a shared list they activated', function () {
    [, $editor, $list, $product] = sharedListSetup(ShoppingList::ROLE_EDITOR);

    $this->actingAs($editor)->post(route('cart.activate', $list))->assertRedirect();
    $this->post(route('cart.items.store', $product))->assertRedirect();

    expect($list->products()->count())->toBe(1);
});

test('viewers can see the list but not change it', function () {
    [, $viewer, $list, $product] = sharedListSetup(ShoppingList::ROLE_VIEWER);
    $list->products()->attach($product->id, ['quantity' => 1]);

    $this->actingAs($viewer)
        ->get(route('cart.show', $list))
        ->assertOk()
        ->assertSee('Fresh Milk')
        ->assertDontSee(route('cart.lists.items.destroy', [$list, $product]));

    $this->post(route('cart.lists.items.store', [$list, $product]))->assertForbidden();
    $this->post(route('cart.lists.items.decrease', [$list, $product]))->assertForbidden();
    $this->delete(route('cart.lists.items.destroy', [$list, $product]))->assertForbidden();
    $this->post(route('cart.activate', $list))->assertForbidden();
});

test('only the owner can manage members, rename or delete the list', function () {
    [, $editor, $list] = sharedListSetup(ShoppingList::ROLE_EDITOR);
    $other = User::factory()->create();

    $this->actingAs($editor)
        ->post(route('cart.members.store', $list), ['email' => $other->email, 'role' => 'editor'])
        ->assertForbidden();
    $this->patch(route('cart.members.update', [$list, $editor]), ['role' => 'editor'])->assertForbidden();
    $this->patch(route('cart.update', $list), ['name' => 'Jauns'])->assertForbidden();
    $this->delete(route('cart.destroy', $list))->assertForbidden();
});

test('owner can change a member role and remove them', function () {
    [$owner, $member, $list, $product] = sharedListSetup(ShoppingList::ROLE_EDITOR);

    $this->actingAs($owner)
        ->patch(route('cart.members.update', [$list, $member]), ['role' => 'viewer'])
        ->assertRedirect();

    expect($list->roleFor($member))->toBe('viewer');

    $this->actingAs($member)
        ->post(route('cart.lists.items.store', [$list, $product]))
        ->assertForbidden();

    $this->actingAs($owner)
        ->delete(route('cart.members.destroy', [$list, $member]))
        ->assertRedirect();

    $this->actingAs($member)
        ->get(route('cart.show', $list))
        ->assertForbidden();
});

test('members can leave a shared list', function () {
    [, $member, $list] = sharedListSetup(ShoppingList::ROLE_VIEWER);

    $this->actingAs($member)
        ->delete(route('cart.members.destroy', [$list, $member]))
        ->assertRedirect(route('cart'));

    expect($list->roleFor($member))->toBeNull();
});

test('owner can create an invite link that lets users join with its role', function () {
    $owner = User::factory()->create();
    $friend = User::factory()->create();
    $list = $owner->shoppingLists()->create(['name' => 'Kopīgais saraksts']);

    $this->actingAs($owner)
        ->post(route('cart.invite.store', $list), ['role' => 'viewer'])
        ->assertRedirect();

    $list->refresh();
    expect($list->invite_token)->not->toBeNull()
        ->and($list->invite_role)->toBe('viewer');

    $this->get(route('cart.show', $list))
        ->assertOk()
        ->assertSee(route('cart.invite.accept', $list->invite_token));

    // Opening the link only asks; it does not add anyone (link previews open links too).
    $this->actingAs($friend)
        ->get(route('cart.invite.accept', $list->invite_token))
        ->assertOk()
        ->assertSee('Pievienoties sarakstam "Kopīgais saraksts"?');

    expect($list->roleFor($friend))->toBeNull();

    $this->post(route('cart.invite.join', $list->invite_token))->assertRedirect(route('cart.show', $list));

    expect($list->roleFor($friend))->toBe('viewer');
});

test('invite links expire', function () {
    [$owner, , $list] = sharedListSetup();
    $stranger = User::factory()->create();

    $this->actingAs($owner)->post(route('cart.invite.store', $list), ['role' => 'editor']);
    $token = $list->refresh()->invite_token;

    expect($list->invite_expires_at->isSameDay(now()->addDays(ShoppingList::INVITE_LINK_DAYS)))->toBeTrue();

    $this->travel(ShoppingList::INVITE_LINK_DAYS + 1)->days();

    $this->actingAs($stranger)->get(route('cart.invite.accept', $token))->assertNotFound();
    $this->post(route('cart.invite.join', $token))->assertNotFound();

    expect($list->roleFor($stranger))->toBeNull();
});

test('joining twice, e.g. from two tabs, adds the user once without an error', function () {
    [$owner, , $list] = sharedListSetup();
    $friend = User::factory()->create();
    $list->forceFill(['invite_token' => 'abc123', 'invite_role' => 'editor', 'invite_expires_at' => now()->addDay()])->save();

    // Both tabs loaded the page before either joined, then both press the button.
    $list->addMember($friend, 'editor');
    $list->addMember($friend, 'editor');
    $this->actingAs($friend)->post(route('cart.invite.join', 'abc123'))->assertRedirect(route('cart.show', $list));

    expect($list->members()->whereKey($friend->id)->count())->toBe(1);
});

test('joining through the link does not change an existing members role', function () {
    [$owner, $member, $list] = sharedListSetup(ShoppingList::ROLE_EDITOR);
    $list->forceFill(['invite_token' => 'abc123', 'invite_role' => 'viewer', 'invite_expires_at' => now()->addDay()])->save();

    $this->actingAs($member)
        ->get(route('cart.invite.accept', 'abc123'))
        ->assertRedirect(route('cart.show', $list));
    $this->post(route('cart.invite.join', 'abc123'))->assertRedirect(route('cart.show', $list));

    expect($list->roleFor($member))->toBe('editor')
        ->and($list->members()->count())->toBe(1);
});

test('disabled or unknown invite links cannot be used', function () {
    [$owner, , $list] = sharedListSetup();
    $list->forceFill(['invite_token' => 'abc123'])->save();
    $stranger = User::factory()->create();

    $this->actingAs($owner)
        ->delete(route('cart.invite.destroy', $list))
        ->assertRedirect();

    expect($list->refresh()->invite_token)->toBeNull();

    $this->actingAs($stranger)
        ->get(route('cart.invite.accept', 'abc123'))
        ->assertNotFound();
});

test('only the owner can create or disable the invite link', function () {
    [, $editor, $list] = sharedListSetup(ShoppingList::ROLE_EDITOR);

    $this->actingAs($editor)
        ->post(route('cart.invite.store', $list), ['role' => 'editor'])
        ->assertForbidden();
    $this->delete(route('cart.invite.destroy', $list))->assertForbidden();
});

test('guests opening an invite link are sent to log in', function () {
    [, , $list] = sharedListSetup();
    $list->forceFill(['invite_token' => 'abc123'])->save();

    $this->get(route('cart.invite.accept', 'abc123'))
        ->assertRedirect(route('login'));
});
