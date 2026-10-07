<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

test('registering sends a verification email', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Jānis',
        'email' => 'janis@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    Notification::assertSentTo(User::query()->where('email', 'janis@example.com')->firstOrFail(), VerifyEmail::class);
});

test('unverified users are sent to the verification page', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->get(route('verification.notice'))->assertOk()->assertSee($user->email);
});

test('the verification link verifies the email', function () {
    Event::fake();
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('a link with the wrong hash does not verify the email', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1('someone-else@example.com'),
    ]);

    $this->actingAs($user)->get($url);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('changing the email address requires verifying it again', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', 'new@example.com')
        ->call('updateProfileInformation');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});
