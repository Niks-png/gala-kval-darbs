<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

function mailServerDown(): void
{
    // Nothing listens on port 1, so sending fails at once with a transport error.
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
}

function signUp(string $email = 'janis@example.com'): TestResponse
{
    return test()->post(route('register.store'), [
        'name' => 'Jānis', 'email' => $email, 'password' => 'password', 'password_confirmation' => 'password',
    ]);
}

test('signing up still works when the mail server is down, and the user is told the email did not go out', function () {
    mailServerDown();

    signUp()->assertRedirect();

    expect(User::query()->where('email', 'janis@example.com')->exists())->toBeTrue()
        ->and(auth()->check())->toBeTrue();

    $this->get(route('verification.notice'))
        ->assertOk()
        ->assertSee('E-pastu neizdevās nosūtīt');

    // Once mail works again, the resend button sends it and the warning goes away.
    config(['mail.default' => 'array']);
    $this->post(route('verification.send'))->assertRedirect();

    $this->get(route('verification.notice'))->assertDontSee('E-pastu neizdevās nosūtīt');
});

test('signing up is limited to five attempts an hour from one address', function () {
    foreach (range(1, 5) as $i) {
        auth()->logout();
        signUp("cilveks{$i}@example.com")->assertSessionDoesntHaveErrors();
    }

    auth()->logout();
    signUp('cilveks6@example.com')->assertSessionHasErrors('email');

    expect(User::query()->count())->toBe(5);

    $this->travel(61)->minutes();
    signUp('cilveks6@example.com')->assertSessionDoesntHaveErrors();
});

test('"forgot password" is limited to five emails an hour from one address', function () {
    // Laravel already allows one reset email per account a minute; this limits many accounts from one place.
    $users = User::factory()->count(6)->create();

    foreach ($users->take(5) as $user) {
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionDoesntHaveErrors();
    }

    $this->post(route('password.email'), ['email' => $users->last()->email])
        ->assertSessionHasErrors(['email' => 'Pārāk daudz mēģinājumu. Mēģini vēlreiz pēc 60 min.']);
});

test('checking email addresses through list invitations is rate limited', function () {
    [$owner, , $list] = sharedListSetup();
    $this->actingAs($owner);

    foreach (range(1, 10) as $i) {
        $this->post(route('cart.members.store', $list), ['email' => "nav{$i}@example.com", 'role' => 'viewer']);
    }

    $this->post(route('cart.members.store', $list), ['email' => 'vel@example.com', 'role' => 'viewer'])->assertTooManyRequests();
});

test('dates are shown in Riga time and purchases count in the Riga month', function () {
    // 30 September 22:30 UTC is already 1 October 01:30 in Riga (summer time, UTC+3).
    Carbon::setTestNow('2026-10-10 12:00:00');
    $user = User::factory()->create();
    $list = $user->shoppingLists()->create(['name' => 'Naktī pabeigts']);
    $list->forceFill(['completed_at' => '2026-09-30 22:30:00', 'completed_total' => 12.00])->save();

    $this->actingAs($user)->get(route('cart'))
        ->assertSeeInOrder(['Iztērēts šomēnes', '12,00 €'])
        ->assertSee('01.10.2026');

    $list->forceFill(['invite_token' => 'abc', 'invite_role' => 'viewer', 'invite_expires_at' => '2026-10-17 09:00:00'])->save();

    $this->get(route('cart.show', $list))->assertSee('17.10.2026 12:00');

    Carbon::setTestNow();
});
