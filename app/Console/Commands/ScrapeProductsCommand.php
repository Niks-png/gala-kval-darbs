<?php

namespace App\Console\Commands;

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
    ];

    /**
     * @var string
     */
    protected $signature = 'products:scrape {store?* : Only these stores (maxima, top)}';

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

            $result = Process::path(base_path())
                ->timeout(config('services.scraper.timeout'))
                ->env(['PYTHONIOENCODING' => 'utf-8'])
                ->run([config('services.scraper.python'), base_path(self::SCRAPERS[$store])]);

            if ($result->successful()) {
                $this->line(trim($result->output()));

                continue;
            }

            $failed[] = $store;
            $this->error("Scraping {$store} failed (exit code {$result->exitCode()}).");
            $this->line(trim($result->errorOutput()));
            Log::error("products:scrape failed for {$store}", [
                'exit_code' => $result->exitCode(),
                'error' => mb_substr($result->errorOutput(), -2000),
            ]);
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
