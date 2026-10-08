<?php

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\User;
use App\Notifications\PriceDropped;
use App\Services\PriceDropNotifier;
use Illuminate\Support\Facades\Notification;

function importCsv(string $rows): void
{
    $path = tempnam(sys_get_temp_dir(), 'products').'.csv';
    file_put_contents($path, "title,store,original_price,current_price,unit_price,unit\n".$rows);

    test()->artisan('products:import', ['file' => $path])->assertSuccessful();

    unlink($path);
}

test('users can follow and unfollow a product from its page', function () {
    $user = User::factory()->create();
    $product = Product::query()->create(['title' => 'Piens', 'store' => 'Rimi', 'current_price' => 1.39]);

    $this->actingAs($user)->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('Sekot cenai');

    $this->post(route('products.watch', $product))->assertRedirect();
    expect($user->isWatching($product))->toBeTrue();

    // following twice does not duplicate
    $this->post(route('products.watch', $product))->assertRedirect();
    expect($user->watchedProducts()->count())->toBe(1);

    $this->get(route('products.show', $product))->assertSee('Seko cenai');

    $this->delete(route('products.unwatch', $product))->assertRedirect();
    expect($user->fresh()->isWatching($product))->toBeFalse();
});

test('product cards show a follow bell reflecting the current state', function () {
    $user = User::factory()->create();
    $followed = Product::query()->create(['title' => 'Piens', 'store' => 'Rimi', 'current_price' => 1.39]);
    Product::query()->create(['title' => 'Maize', 'store' => 'Rimi', 'current_price' => 0.99]);
    $user->watchedProducts()->attach($followed);

    $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

    expect(substr_count($html, 'data-test="card-watch-button"'))->toBe(2)
        ->and(substr_count($html, 'aria-pressed="true"'))->toBe(1)
        ->and($html)->toContain('value="DELETE"');
});

test('the card bell toggles over JSON without a redirect', function () {
    $user = User::factory()->create();
    $product = Product::query()->create(['title' => 'Piens', 'store' => 'Rimi', 'current_price' => 1.39]);

    $this->actingAs($user)->postJson(route('products.watch', $product))
        ->assertOk()
        ->assertJson(['watching' => true]);
    expect($user->isWatching($product))->toBeTrue();

    $this->deleteJson(route('products.unwatch', $product))
        ->assertOk()
        ->assertJson(['watching' => false]);
    expect($user->isWatching($product))->toBeFalse();
});

test('guests cannot follow products', function () {
    $product = Product::query()->create(['title' => 'Piens', 'store' => 'Rimi', 'current_price' => 1.39]);

    $this->post(route('products.watch', $product))->assertRedirect(route('login'));
});

test('importing a lower price notifies followers only', function () {
    $follower = User::factory()->create();
    $other = User::factory()->create();
    $product = Product::query()->create(['title' => 'Piens Rasa', 'store' => 'Rimi', 'current_price' => 1.39]);
    $follower->watchedProducts()->attach($product);

    importCsv("Piens Rasa,Rimi,,1.19,1.19,€/l\n");

    expect($follower->notifications()->count())->toBe(1)
        ->and($other->notifications()->count())->toBe(0);

    $data = $follower->notifications()->first()->data;
    expect($data['product_id'])->toBe($product->id)
        ->and($data['previous_price'])->toEqual(1.39)
        ->and($data['new_price'])->toEqual(1.19)
        ->and($data['drop_percent'])->toBe(14)
        ->and($data['lowest_30_days'])->toBeTrue();
});

test('price increases and unchanged prices do not notify', function () {
    $follower = User::factory()->create();
    $product = Product::query()->create(['title' => 'Maize', 'store' => 'Rimi', 'current_price' => 1.29]);
    $follower->watchedProducts()->attach($product);

    importCsv("Maize,Rimi,,1.49,,\n");
    importCsv("Maize,Rimi,,1.49,,\n");

    expect($follower->notifications()->count())->toBe(0);
});

test('a drop that is not the 30 day low is not flagged as one', function () {
    $follower = User::factory()->create();
    $product = Product::query()->create(['title' => 'Olas', 'store' => 'Rimi', 'current_price' => 1.99]);
    $follower->watchedProducts()->attach($product);
    ProductPriceHistory::query()->create(['product_id' => $product->id, 'previous_price' => 2.19, 'new_price' => 1.79])->forceFill(['alerts_sent_at' => now()])->save();
    ProductPriceHistory::query()->create(['product_id' => $product->id, 'previous_price' => 1.79, 'new_price' => 2.29]);
    $product->update(['current_price' => 2.29]);

    importCsv("Olas,Rimi,,1.99,,\n");

    expect($follower->notifications()->first()->data['lowest_30_days'])->toBeFalse();
});

test('a drop nobody follows is marked as handled without sending anything', function () {
    Notification::fake();
    $product = Product::query()->create(['title' => 'Siers', 'store' => 'Rimi', 'current_price' => 2.00]);
    $drop = ProductPriceHistory::query()->create(['product_id' => $product->id, 'previous_price' => 2.50, 'new_price' => 2.00]);

    expect(app(PriceDropNotifier::class)->sendPending())->toBe(0);
    Notification::assertNothingSent();
    expect($drop->fresh()->alerts_sent_at)->not->toBeNull();
});

test('each price drop is announced exactly once', function () {
    $follower = User::factory()->create();
    $product = Product::query()->create(['title' => 'Piens', 'store' => 'Rimi', 'current_price' => 1.39]);
    $follower->watchedProducts()->attach($product);

    importCsv("Piens,Rimi,,1.19,,\n");
    app(PriceDropNotifier::class)->sendPending();
    importCsv("Piens,Rimi,,1.19,,\n");

    expect($follower->notifications()->count())->toBe(1);
});

test('alerts that fail to send do not undo the import and go out with the next import', function () {
    $follower = User::factory()->create();
    $product = Product::query()->create(['title' => 'Piens', 'store' => 'Rimi', 'current_price' => 1.39]);
    $follower->watchedProducts()->attach($product);

    $this->mock(PriceDropNotifier::class)->shouldReceive('sendPending')->andThrow(new RuntimeException('mail server down'));
    importCsv("Piens,Rimi,,1.19,,\n");

    expect($product->fresh()->current_price)->toBe('1.19')
        ->and($product->priceHistory()->sole()->alerts_sent_at)->toBeNull()
        ->and($follower->notifications()->count())->toBe(0);

    $this->app->forgetInstance(PriceDropNotifier::class);
    $this->app->offsetUnset(PriceDropNotifier::class);
    importCsv("Piens,Rimi,,1.19,,\n");

    expect($follower->notifications()->count())->toBe(1);
});

test('notifications page shows alerts, marks them read and counts them in the badge', function () {
    $user = User::factory()->create();
    $product = Product::query()->create(['title' => 'Piens Rasa', 'store' => 'Rimi', 'current_price' => 1.19]);
    $user->watchedProducts()->attach($product);
    $user->notify(new PriceDropped($product, 1.39, 1.19, true));

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Paziņojumi');
    expect($user->unreadNotifications()->count())->toBe(1);

    $this->get(route('notifications'))
        ->assertOk()
        ->assertSee('Cenu kritumi')
        ->assertSee('Piens Rasa')
        ->assertSee('1,39 €')
        ->assertSee('1,19 €')
        ->assertSee('−14%')
        ->assertSee('Jauns')
        ->assertSee('Produkti, kuriem seko');

    expect($user->unreadNotifications()->count())->toBe(0);

    $this->get(route('notifications'))->assertDontSee('Jauns');
});

test('users can delete their own alerts but not someone elses', function () {
    $user = User::factory()->create();
    $intruder = User::factory()->create();
    $product = Product::query()->create(['title' => 'Piens', 'store' => 'Rimi', 'current_price' => 1.19]);
    $user->notify(new PriceDropped($product, 1.39, 1.19));
    $alert = $user->notifications()->first();

    $this->actingAs($intruder)->delete(route('notifications.destroy', $alert->id));
    expect($user->notifications()->count())->toBe(1);

    $this->actingAs($user)->delete(route('notifications.destroy', $alert->id))->assertRedirect();
    expect($user->notifications()->count())->toBe(0);

    $user->notify(new PriceDropped($product, 1.39, 1.19));
    $user->notify(new PriceDropped($product, 1.19, 0.99));
    $this->delete(route('notifications.destroy-all'))->assertRedirect();
    expect($user->notifications()->count())->toBe(0);
});

test('only the alerts shown are marked as read; the rest stay unread for later pages', function () {
    $user = User::factory()->create();
    $product = Product::query()->create(['title' => 'Piens', 'store' => 'Rimi', 'current_price' => 1.19]);

    foreach (range(1, 25) as $i) {
        $this->travel(1)->minute();
        $user->notify(new PriceDropped($product, 1.39, 1.19));
    }

    $this->actingAs($user)->get(route('notifications'))->assertOk();

    expect($user->unreadNotifications()->count())->toBe(5);

    $this->get(route('notifications', ['page' => 2]))->assertOk();

    expect($user->unreadNotifications()->count())->toBe(0);
});
