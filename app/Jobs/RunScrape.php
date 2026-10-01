<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * Runs products:scrape from the admin panel. Queued, because a full scrape
 * takes minutes and would time out a web request.
 */
class RunScrape implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    /**
     * @param  list<string>  $stores  Empty means every store
     */
    public function __construct(public array $stores = []) {}

    public function handle(): void
    {
        // Same log the daily scheduled scrape appends to.
        $log = fopen(storage_path('logs/scrape.log'), 'a');

        if ($log === false) {
            throw new RuntimeException('Cannot open storage/logs/scrape.log for writing.');
        }

        try {
            Artisan::call('products:scrape', ['store' => $this->stores], new StreamOutput($log));
        } finally {
            fclose($log);
        }
    }
}
