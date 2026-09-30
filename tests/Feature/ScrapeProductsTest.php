<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

test('products:scrape runs every store scraper', function () {
    config(['services.scraper.python' => 'py']);
    Process::fake();

    $this->artisan('products:scrape')->assertSuccessful();

    Process::assertRanTimes(fn (PendingProcess $process) => $process->command[0] === 'py', 2);
    Process::assertRan(fn (PendingProcess $process) => str_ends_with($process->command[1], 'maxima_scraper.py'));
    Process::assertRan(fn (PendingProcess $process) => str_ends_with($process->command[1], 'top_scraper.py'));
});

test('products:scrape can run a single store', function () {
    Process::fake();

    $this->artisan('products:scrape', ['store' => ['top']])->assertSuccessful();

    Process::assertRanTimes(fn () => true, 1);
    Process::assertRan(fn (PendingProcess $process) => str_ends_with($process->command[1], 'top_scraper.py'));
});

test('a failing scraper does not stop the others but fails the command', function () {
    Process::fake([
        '*maxima_scraper.py*' => Process::result(errorOutput: 'Chrome not found', exitCode: 1),
        '*' => Process::result('Saved 10 top! products'),
    ]);

    $this->artisan('products:scrape')
        ->expectsOutputToContain('Scraping maxima failed')
        ->assertFailed();

    Process::assertRan(fn (PendingProcess $process) => str_ends_with($process->command[1], 'top_scraper.py'));
});

test('products:scrape rejects unknown stores', function () {
    Process::fake();

    $this->artisan('products:scrape', ['store' => ['lidl']])->assertFailed();

    Process::assertNothingRan();
});

test('products:scrape is scheduled daily', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'products:scrape'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 6 * * *')
        ->and($event->timezone)->toBe('Europe/Riga');
});
