<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * Runs products:scrape from the admin panel. Queued, because a full scrape
 * takes minutes and would time out a web request.
 *
 * Unique, so clicking "update" twice queues it once. products:scrape also locks
 * each store itself, which covers overlap with the daily scheduled run.
 */
class RunScrape implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    /**
     * Keep the uniqueness lock no longer than a run can take.
     */
    public int $uniqueFor = 1800;

    /**
     * @param  list<string>  $stores  Empty means every store
     */
    public function __construct(public array $stores = []) {}

    public function uniqueId(): string
    {
        return $this->stores === [] ? 'all' : implode(',', $this->stores);
    }

    public function handle(): void
    {
        // Same log the daily scheduled scrape appends to.
        $log = fopen(storage_path('logs/scrape.log'), 'a');

        if ($log === false) {
            throw new RuntimeException('Cannot open storage/logs/scrape.log for writing.');
        }

        try {
            $exitCode = Artisan::call('products:scrape', ['store' => $this->stores], new StreamOutput($log));
        } finally {
            fclose($log);
        }

        // Otherwise the queue would record a failed price update as a successful job.
        if ($exitCode !== 0) {
            throw new RuntimeException('products:scrape failed for '.($this->stores === [] ? 'one or more stores' : implode(', ', $this->stores)).'; see the admin panel or storage/logs/scrape.log.');
        }
    }
}
