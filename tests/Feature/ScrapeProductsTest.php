<?php

use App\Models\Product;
use App\Models\ScrapeRun;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Process\PendingProcess;
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

    Process::assertRan(fn (PendingProcess $process) => str_ends_with($process->command[2], 'rimi_products.csv'));
    expect(Product::query()->orderBy('store')->pluck('store')->all())->toBe(['rimi.lv', 'top.lv']);
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
