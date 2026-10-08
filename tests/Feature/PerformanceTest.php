<?php

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/**
 * How many rows of $model a request turns into models.
 */
function modelsLoaded(string $model, Closure $request): int
{
    $count = 0;
    Event::listen("eloquent.retrieved: {$model}", function () use (&$count) {
        $count++;
    });

    $request();

    Event::forget("eloquent.retrieved: {$model}");

    return $count;
}

function productWithDailyHistory(int $days): Product
{
    $product = Product::query()->create(['title' => 'Piens', 'store' => 'rimi.lv', 'current_price' => 1.19]);

    ProductPriceHistory::insert(collect(range(1, $days))->map(fn (int $day) => [
        'product_id' => $product->id,
        'previous_price' => 1.29,
        'new_price' => 1.19,
        'alerts_sent_at' => now(),
        'created_at' => now()->subDays($day)->addHour(),
        'updated_at' => now(),
    ])->all());

    return $product;
}

test('product cards load only the last 30 days of price history, not all of it', function () {
    productWithDailyHistory(200);
    $this->actingAs(User::factory()->create());

    $loaded = modelsLoaded(ProductPriceHistory::class, fn () => $this->get(route('dashboard'))->assertOk());

    expect($loaded)->toBe(30);
});

test('the product page loads only the chosen history period', function () {
    $product = productWithDailyHistory(200);
    $this->actingAs(User::factory()->create());

    expect(modelsLoaded(ProductPriceHistory::class, fn () => $this->get(route('products.show', $product))->assertOk()))->toBe(90)
        ->and(modelsLoaded(ProductPriceHistory::class, fn () => $this->get(route('products.show', [$product, 'period' => 30]))->assertOk()))->toBe(30)
        ->and(modelsLoaded(ProductPriceHistory::class, fn () => $this->get(route('products.show', [$product, 'period' => 365]))->assertOk()))->toBe(200)
        // Unknown periods fall back to the default.
        ->and(modelsLoaded(ProductPriceHistory::class, fn () => $this->get(route('products.show', [$product, 'period' => 99999]))->assertOk()))->toBe(90);
});

test('the lists page shows counts and totals without loading the products', function () {
    $user = User::factory()->create();
    $list = $user->shoppingLists()->create(['name' => 'Nedēļas iepirkumi']);
    $milk = Product::query()->create(['title' => 'Piens', 'store' => 'rimi.lv', 'current_price' => 1.50]);
    $bread = Product::query()->create(['title' => 'Maize', 'store' => 'rimi.lv', 'current_price' => 2.00]);
    $list->products()->attach([$milk->id => ['quantity' => 2], $bread->id => ['quantity' => 1]]);
    $this->actingAs($user);

    $loaded = modelsLoaded(Product::class, fn () => $this->get(route('cart'))
        ->assertOk()
        ->assertSee('3 preces')
        ->assertSee('5,00 €'));

    expect($loaded)->toBe(0);
});

test('finished lists are shown ten per page', function () {
    $user = User::factory()->create();
    foreach (range(1, 15) as $i) {
        $user->shoppingLists()->create(['name' => sprintf('Vēsture %02d', $i)])
            ->forceFill(['completed_at' => now()->subDays($i), 'completed_total' => 10])->save();
    }

    $this->actingAs($user)->get(route('cart'))
        ->assertSee('Vēsture 01')
        ->assertSee('Vēsture 10')
        ->assertDontSee('Vēsture 11');

    $this->get(route('cart', ['history' => 2]))->assertSee('Vēsture 15');
});

test('an import larger than one database batch saves every product and price change', function () {
    $rows = collect(range(1, 1200))->map(fn (int $i) => sprintf('Prece %04d,rimi.lv,,1.00,,', $i))->implode("\n");
    importCsv($rows);

    $this->travel(1)->day();
    importCsv(str_replace(',1.00,', ',0.90,', $rows));

    expect(Product::query()->count())->toBe(1200)
        ->and(Product::query()->where('current_price', 0.90)->count())->toBe(1200)
        ->and(ProductPriceHistory::query()->count())->toBe(1200);
});
