<?php

use App\Exceptions\LastAdminException;
use App\Jobs\RunScrape;
use App\Models\Product;
use App\Models\ScrapeRun;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('guests and regular users cannot open the admin panel', function () {
    $this->get(route('admin.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create());

    $this->get(route('admin.index'))->assertForbidden();
    $this->get(route('admin.users'))->assertForbidden();
    $this->post(route('admin.scrape'))->assertForbidden();
});

test('only admins see the admin link in the sidebar', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertDontSee(route('admin.index'));
    $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))->assertSee(route('admin.index'));
});

test('the admin overview shows store stats and the last scrape of each store', function () {
    Product::query()->create(['title' => 'Piens', 'store' => 'rimi.lv', 'current_price' => 0.99, 'original_price' => '1.39']);
    Product::query()->create(['title' => 'Maize', 'store' => 'rimi.lv', 'current_price' => 1.19]);
    ScrapeRun::query()->create([
        'store' => 'maxima', 'status' => ScrapeRun::STATUS_FAILED, 'exit_code' => 1,
        'error' => 'Only 3 Maxima offers loaded', 'started_at' => now()->subMinutes(5), 'finished_at' => now(),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.index'))
        ->assertOk()
        ->assertSeeInOrder(['rimi.lv', '2', '1'])
        ->assertSee('maxima.lv')
        ->assertDontSee('lidl.lv')
        ->assertSee('Neizdevās')
        ->assertSee('Only 3 Maxima offers loaded');
});

test('admins can queue a scrape for one store or all stores', function () {
    Queue::fake();
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('admin.scrape'), ['store' => 'rimi'])->assertRedirect();
    Queue::assertPushed(RunScrape::class, fn (RunScrape $job) => $job->stores === ['rimi']);

    $this->post(route('admin.scrape'))->assertRedirect();
    Queue::assertPushed(RunScrape::class, fn (RunScrape $job) => $job->stores === []);

    $this->post(route('admin.scrape'), ['store' => 'aldi'])->assertSessionHasErrors('store');
    Queue::assertPushedTimes(RunScrape::class, 2);
});

test('a second update is refused while one is running, unless that run crashed long ago', function () {
    Queue::fake();
    $this->actingAs(User::factory()->admin()->create());
    $run = ScrapeRun::query()->create(['store' => 'rimi', 'status' => ScrapeRun::STATUS_RUNNING, 'started_at' => now()->subMinutes(5)]);

    $this->post(route('admin.scrape'))->assertSessionHas('error');
    Queue::assertNothingPushed();

    // Older than the scrape timeout: the process died, so it must not block updates forever.
    $run->update(['started_at' => now()->subHours(3)]);

    $this->post(route('admin.scrape'))->assertSessionHas('success');
    Queue::assertPushed(RunScrape::class);
});

test('products:scrape records each store run for the admin panel', function () {
    fakeScrapers(['maxima' => 'Maxima site changed']);

    $this->artisan('products:scrape', ['store' => ['maxima', 'rimi']])->assertFailed();

    expect(ScrapeRun::query()->where('store', 'rimi')->sole())
        ->status->toBe(ScrapeRun::STATUS_SUCCESS)
        ->finished_at->not->toBeNull()
        ->and(ScrapeRun::query()->where('store', 'maxima')->sole())
        ->status->toBe(ScrapeRun::STATUS_FAILED)
        ->error->toContain('Maxima site changed');
});

test('admins can search users and change their role', function () {
    $this->actingAs($admin = User::factory()->admin()->create());
    $user = User::factory()->create(['name' => 'Anna Bērziņa']);
    User::factory()->create(['name' => 'Jānis Kalniņš']);

    $this->get(route('admin.users', ['q' => 'Anna']))
        ->assertOk()
        ->assertSee('Anna Bērziņa')
        ->assertDontSee('Jānis Kalniņš');

    $this->patch(route('admin.users.update', $user), ['is_admin' => 1])->assertRedirect();
    expect($user->fresh()->is_admin)->toBeTrue();

    $this->patch(route('admin.users.update', $user), ['is_admin' => 0])->assertRedirect();
    expect($user->fresh()->is_admin)->toBeFalse();
});

test('admins can delete users together with their lists, but not themselves', function () {
    $this->actingAs($admin = User::factory()->admin()->create());
    $user = User::factory()->create();
    $list = $user->shoppingLists()->create(['name' => 'Nedēļa']);

    $this->delete(route('admin.users.destroy', $user))->assertRedirect();

    expect(User::query()->find($user->id))->toBeNull()
        ->and(ShoppingList::query()->find($list->id))->toBeNull();

    $this->delete(route('admin.users.destroy', $admin))->assertForbidden();
    $this->patch(route('admin.users.update', $admin), ['is_admin' => 0])->assertForbidden();
    expect($admin->fresh()->is_admin)->toBeTrue();
});

test('users:admin grants and revokes admin rights', function () {
    User::factory()->admin()->create();
    $user = User::factory()->create(['email' => 'anna@example.com']);

    $this->artisan('users:admin', ['email' => 'anna@example.com'])->assertSuccessful();
    expect($user->fresh()->is_admin)->toBeTrue();

    $this->artisan('users:admin', ['email' => 'anna@example.com', '--revoke' => true])->assertSuccessful();
    expect($user->fresh()->is_admin)->toBeFalse();

    $this->artisan('users:admin', ['email' => 'nobody@example.com'])->assertFailed();
});

test('is_admin cannot be set through mass assignment', function () {
    $user = User::query()->create([
        'name' => 'Hacker', 'email' => 'h@example.com', 'password' => 'password', 'is_admin' => true,
    ]);

    expect($user->fresh()->is_admin)->toBeFalse();
});

test('the last admin cannot lose admin rights or be deleted, from the panel, the console or settings', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $this->actingAs($admin);

    // With two admins, one can be demoted.
    $this->patch(route('admin.users.update', $other), ['is_admin' => false])->assertSessionHas('success');
    expect($other->refresh()->is_admin)->toBeFalse();

    // Now $admin is the only one left.
    expect($admin->revokeAdmin())->toBeFalse()
        ->and(fn () => $admin->delete())->toThrow(LastAdminException::class);

    $this->artisan('users:admin', ['email' => $admin->email, '--revoke' => true])
        ->expectsOutputToContain('is the last admin')
        ->assertFailed();

    Livewire\Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasErrors('password');

    expect($admin->refresh()->is_admin)->toBeTrue()
        ->and(User::query()->whereKey($admin->id)->exists())->toBeTrue();
});
