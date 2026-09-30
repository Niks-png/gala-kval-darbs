<?php

use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('registration screen lists the configured password requirements', function () {
    Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('data-password-requirements', false)
        ->assertSee('data-min="12"', false)
        ->assertSee('data-rule="lower"', false)
        ->assertSee('data-rule="upper"', false)
        ->assertSee('data-rule="numbers"', false)
        ->assertSee('data-rule="symbols"', false)
        ->assertSee('data-rule="match"', false);
});

test('password requirements only list rules that are enforced', function () {
    Password::defaults(fn () => Password::min(8));

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('data-min="8"', false)
        ->assertDontSee('data-rule="upper"', false)
        ->assertDontSee('data-rule="symbols"', false);
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});
