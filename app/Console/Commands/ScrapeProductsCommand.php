<?php

namespace App\Console\Commands;

use App\Models\ScrapeRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ScrapeProductsCommand extends Command
{
    /**
     * Each scraper writes its CSV and then runs products:import itself.
     */
    public const SCRAPERS = [
        'maxima' => 'public/maxima_scraper.py',
        'top' => 'public/top_scraper.py',
        'rimi' => 'public/rimi_scraper.py',
        'lidl' => 'public/lidl_scraper.py',
    ];

    /**
     * The store value each scraper writes to the products table.
     */
    public const STORE_DOMAINS = [
        'maxima' => 'maxima.lv',
        'top' => 'etop.lv',
        'rimi' => 'rimi.lv',
        'lidl' => 'lidl.lv',
    ];

    /**
     * @var string
     */
    protected $signature = 'products:scrape {store?* : Only these stores (maxima, top, rimi, lidl)}';

    /**
     * @var string
     */
    protected $description = 'Scrape store prices and import them (runs daily from the scheduler)';

    public function handle(): int
    {
        $stores = $this->argument('store') ?: array_keys(self::SCRAPERS);
        $unknown = array_diff($stores, array_keys(self::SCRAPERS));

        if ($unknown !== []) {
            $this->error('Unknown store: '.implode(', ', $unknown).'. Use: '.implode(', ', array_keys(self::SCRAPERS)));

            return self::FAILURE;
        }

        $failed = [];

        // One failing store must not stop the others from updating.
        foreach ($stores as $store) {
            $this->info("Scraping {$store}...");
            $run = ScrapeRun::query()->create([
                'store' => $store,
                'status' => ScrapeRun::STATUS_RUNNING,
                'started_at' => now(),
            ]);

            // Stream the scraper's progress as it runs so a long scrape doesn't look frozen.
            $result = Process::path(base_path())
                ->timeout(config('services.scraper.timeout'))
                ->env(['PYTHONIOENCODING' => 'utf-8', 'PYTHONUNBUFFERED' => '1'])
                ->run(
                    [config('services.scraper.python'), base_path(self::SCRAPERS[$store])],
                    fn (string $type, string $output) => $this->output->write($output),
                );

            $run->update([
                'status' => $result->successful() ? ScrapeRun::STATUS_SUCCESS : ScrapeRun::STATUS_FAILED,
                'exit_code' => $result->exitCode(),
                'error' => $result->successful() ? null : mb_substr($result->errorOutput(), -2000),
                'finished_at' => now(),
            ]);

            if ($result->successful()) {
                continue;
            }

            $failed[] = $store;
            $this->error("Scraping {$store} failed (exit code {$result->exitCode()}).");
            Log::error("products:scrape failed for {$store}", [
                'exit_code' => $result->exitCode(),
                'error' => mb_substr($result->errorOutput(), -2000),
            ]);
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
