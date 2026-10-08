<?php

use App\Models\Product;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Fake the Python store scrapers. Each run saves a one-product CSV where products:scrape
 * asks for it (in a temporary folder), unless $failures gives that store an error message.
 *
 * @param  array<string, string>  $failures  Store key ("maxima") => error output
 */
function fakeScrapers(array $failures = []): void
{
    config(['services.scraper.output_dir' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'scrapers-'.uniqid()]);

    Process::fake(function (PendingProcess $process) use ($failures) {
        [, $script, $csvPath] = $process->command;
        $store = basename($script, '_scraper.py');

        if (isset($failures[$store])) {
            return Process::result(errorOutput: $failures[$store], exitCode: 1);
        }

        if (! is_dir(dirname($csvPath))) {
            mkdir(dirname($csvPath), recursive: true);
        }

        file_put_contents($csvPath, "title,store,original_price,current_price,unit_price,unit\nPiens,{$store}.lv,,0.99,,\n");

        return Process::result("Saved 1 {$store} products");
    });
}

/**
 * A list owned by one user with a second user as $role, plus a product.
 *
 * @return array{0: User, 1: User, 2: ShoppingList, 3: Product}
 */
function sharedListSetup(string $role = ShoppingList::ROLE_EDITOR): array
{
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $list = $owner->shoppingLists()->create(['name' => 'Kopīgais saraksts']);
    $list->members()->attach($member->id, ['role' => $role]);
    $product = Product::query()->create(['title' => 'Fresh Milk', 'store' => 'etop.lv', 'current_price' => 1.99]);

    return [$owner, $member, $list, $product];
}
