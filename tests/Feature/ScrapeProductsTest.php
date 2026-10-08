<?php

use App\Jobs\RunScrape;
use App\Models\Product;
use App\Models\ScrapeRun;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

test('products:scrape runs every enabled store scraper', function () {
    config(['services.scraper.python' => 'py']);
    fakeScrapers();

    $this->artisan('products:scrape')->assertSuccessful();

    Process::assertRanTimes(fn (PendingProcess $process) => $process->command[0] === 'py', 3);
    Process::assertRan(fn (PendingProcess $process) => str_ends_with($process->command[1], 'maxima_scraper.py'));
    Process::assertRan(fn (PendingProcess $process) => str_ends_with($process->command[1], 'top_scraper.py'));
    Process::assertRan(fn (PendingProcess $process) => str_ends_with($process->command[1], 'rimi_scraper.py'));
    Process::assertDidntRun(fn (PendingProcess $process) => str_ends_with($process->command[1], 'lidl_scraper.py'));
});

test('a disabled store is refused until it is enabled', function () {
    fakeScrapers();

    $this->artisan('products:scrape', ['store' => ['lidl']])
        ->expectsOutputToContain('Unknown or disabled store: lidl')
        ->assertFailed();
    Process::assertNothingRan();

    config(['services.scraper.stores' => ['maxima', 'top', 'rimi', 'lidl']]);

    $this->artisan('products:scrape', ['store' => ['lidl']])->assertSuccessful();
    Process::assertRan(fn (PendingProcess $process) => str_ends_with($process->command[1], 'lidl_scraper.py'));
});

test('products:scrape tells each scraper where to save and imports that file', function () {
    fakeScrapers();

    $this->artisan('products:scrape', ['store' => ['rimi', 'top']])->assertSuccessful();

    $rimiRun = ScrapeRun::query()->where('store', 'rimi')->sole();
    Process::assertRan(fn (PendingProcess $process) => str_ends_with($process->command[2], "rimi_products_{$rimiRun->id}.csv"));
    expect(Product::query()->orderBy('store')->pluck('store')->all())->toBe(['rimi.lv', 'top.lv']);
});

test('every run writes its own file and deletes it after the import', function () {
    fakeScrapers();

    $this->artisan('products:scrape', ['store' => ['rimi']])->assertSuccessful();
    $this->artisan('products:scrape', ['store' => ['rimi']])->assertSuccessful();

    $paths = ScrapeRun::query()->pluck('id')->map(
        fn (int $id) => config('services.scraper.output_dir').DIRECTORY_SEPARATOR."rimi_products_{$id}.csv"
    );

    expect($paths)->toHaveCount(2);
    foreach ($paths as $path) {
        Process::assertRan(fn (PendingProcess $process) => $process->command[2] === $path);
        expect(file_exists($path))->toBeFalse();
    }
});

test('a store that is already being updated is skipped, not scraped twice', function () {
    fakeScrapers();
    $otherRun = Cache::lock('products-scrape:rimi', 60);
    $otherRun->get();

    $this->artisan('products:scrape', ['store' => ['rimi', 'top']])
        ->expectsOutputToContain('Skipped rimi: another price update of this store is still running')
        ->assertFailed();

    Process::assertDidntRun(fn (PendingProcess $process) => str_ends_with($process->command[1], 'rimi_scraper.py'));
    Process::assertRan(fn (PendingProcess $process) => str_ends_with($process->command[1], 'top_scraper.py'));

    // Once the other run finishes, the store can be updated again.
    $otherRun->release();
    $this->artisan('products:scrape', ['store' => ['rimi']])->assertSuccessful();
});

test('an import refused as incomplete fails the run and says why', function () {
    foreach (range(1, 30) as $i) {
        Product::query()->create(['title' => "Prece {$i}", 'store' => 'rimi.lv', 'current_price' => 1.00]);
    }
    fakeScrapers();

    $this->artisan('products:scrape', ['store' => ['rimi']])->assertFailed();

    expect(ScrapeRun::query()->sole())
        ->status->toBe(ScrapeRun::STATUS_FAILED)
        ->error->toContain('but 30 are on offer now')
        ->and(Product::query()->onOffer()->count())->toBe(30);
});

test('the queued scrape job fails when the scrape fails', function () {
    fakeScrapers(['rimi' => 'Rimi site changed']);

    expect(fn () => (new RunScrape(['rimi']))->handle())->toThrow(RuntimeException::class, 'products:scrape failed for rimi');
});

test('products:scrape can run a single store', function () {
    fakeScrapers();

    $this->artisan('products:scrape', ['store' => ['top']])->assertSuccessful();

    Process::assertRanTimes(fn () => true, 1);
    Process::assertRan(fn (PendingProcess $process) => str_ends_with($process->command[1], 'top_scraper.py'));
});

test('a failing scraper does not stop the others but fails the command', function () {
    fakeScrapers(['maxima' => 'Maxima site changed']);

    $this->artisan('products:scrape')
        ->expectsOutputToContain('Scraping maxima failed')
        ->assertFailed();

    Process::assertRan(fn (PendingProcess $process) => str_ends_with($process->command[1], 'top_scraper.py'));
    expect(Product::query()->where('store', 'maxima.lv')->exists())->toBeFalse()
        ->and(Product::query()->where('store', 'top.lv')->exists())->toBeTrue();
});

test('a CSV the import cannot read fails that store', function () {
    config(['services.scraper.output_dir' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'scrapers-'.uniqid()]);
    Process::fake(function (PendingProcess $process) {
        mkdir(dirname($process->command[2]), recursive: true);
        file_put_contents($process->command[2], "title,price\nPiens,0.99\n");

        return Process::result();
    });

    $this->artisan('products:scrape', ['store' => ['rimi']])->assertFailed();

    expect(ScrapeRun::query()->sole())
        ->status->toBe(ScrapeRun::STATUS_FAILED)
        ->error->toContain('missing columns');
});

test('products:scrape rejects unknown stores', function () {
    Process::fake();

    $this->artisan('products:scrape', ['store' => ['aldi']])->assertFailed();

    Process::assertNothingRan();
});

test('products:scrape is scheduled daily', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'products:scrape'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 6 * * *')
        ->and($event->timezone)->toBe('Europe/Riga');
});
