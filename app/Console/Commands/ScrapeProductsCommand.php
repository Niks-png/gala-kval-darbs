<?php

namespace App\Console\Commands;

use App\Models\ScrapeRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

class ScrapeProductsCommand extends Command
{
    /**
     * Each scraper only saves a CSV (to the path it is given); this command then imports it.
     */
    public const SCRAPERS = [
        'maxima' => 'scrapers/maxima_scraper.py',
        'top' => 'scrapers/top_scraper.py',
        'rimi' => 'scrapers/rimi_scraper.py',
        'lidl' => 'scrapers/lidl_scraper.py',
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
    protected $signature = 'products:scrape {store?* : Only these stores (default: every enabled store)}';

    /**
     * @var string
     */
    protected $description = 'Scrape store prices and import them (runs daily from the scheduler)';

    /**
     * Scraper keys turned on in config('services.scraper.stores'), in SCRAPERS order.
     *
     * @return list<string>
     */
    public static function enabledStores(): array
    {
        return array_values(array_intersect(array_keys(self::SCRAPERS), config('services.scraper.stores')));
    }

    public function handle(): int
    {
        $enabled = self::enabledStores();
        $stores = $this->argument('store') ?: $enabled;
        $unknown = array_diff($stores, $enabled);

        if ($unknown !== []) {
            $this->error('Unknown or disabled store: '.implode(', ', $unknown).'. Use: '.implode(', ', $enabled).' (enable more with SCRAPER_STORES).');

            return self::FAILURE;
        }

        ScrapeRun::failStaleRuns();

        $failed = [];

        // One failing store must not stop the others from updating.
        foreach ($stores as $store) {
            // One update per store at a time, whether the scheduler or the admin panel started it.
            $lock = Cache::lock("products-scrape:{$store}", (int) config('services.scraper.timeout') + 300);

            if (! $lock->get()) {
                $failed[] = $store;
                $this->error("Skipped {$store}: another price update of this store is still running.");

                continue;
            }

            try {
                if (! $this->updateStore($store)) {
                    $failed[] = $store;
                }
            } finally {
                $lock->release();
            }
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Scrape one store and import the result, recording the run for the admin panel.
     */
    private function updateStore(string $store): bool
    {
        $this->info("Scraping {$store}...");
        $run = ScrapeRun::query()->create([
            'store' => $store,
            'status' => ScrapeRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        // A file of its own, so no other run can overwrite it before it is imported.
        $csvPath = $this->csvPath($store, $run->id);

        try {
            [$exitCode, $error] = $this->scrape($store, $csvPath);

            if ($exitCode === 0) {
                [$exitCode, $error] = $this->import($csvPath);
            }
        } finally {
            if (is_file($csvPath)) {
                unlink($csvPath);
            }
        }

        $run->update([
            'status' => $exitCode === 0 ? ScrapeRun::STATUS_SUCCESS : ScrapeRun::STATUS_FAILED,
            'exit_code' => $exitCode,
            'error' => $error,
            'finished_at' => now(),
        ]);

        if ($exitCode === 0) {
            return true;
        }

        $this->error("Scraping {$store} failed (exit code {$exitCode}).");
        Log::error("products:scrape failed for {$store}", [
            'exit_code' => $exitCode,
            'error' => $error,
        ]);

        return false;
    }

    private function csvPath(string $store, int $runId): string
    {
        return rtrim(config('services.scraper.output_dir'), '\\/').DIRECTORY_SEPARATOR."{$store}_products_{$runId}.csv";
    }

    /**
     * Run the store's scraper, which saves its offers to $csvPath.
     *
     * @return array{int, string|null} Exit code and error output
     */
    private function scrape(string $store, string $csvPath): array
    {
        // Stream the scraper's progress as it runs so a long scrape doesn't look frozen.
        $result = Process::path(base_path())
            ->timeout(config('services.scraper.timeout'))
            ->env(['PYTHONIOENCODING' => 'utf-8', 'PYTHONUNBUFFERED' => '1'])
            ->run(
                [config('services.scraper.python'), base_path(self::SCRAPERS[$store]), $csvPath],
                fn (string $type, string $output) => $this->output->write($output),
            );

        return [(int) $result->exitCode(), $result->successful() ? null : mb_substr($result->errorOutput(), -2000)];
    }

    /**
     * @return array{int, string|null} Exit code and error message
     */
    private function import(string $csvPath): array
    {
        try {
            $exitCode = $this->call('products:import', ['file' => $csvPath]);
        } catch (Throwable $exception) {
            // E.g. the file has far fewer products than the store has on offer now.
            return [self::FAILURE, 'Import failed: '.$exception->getMessage()];
        }

        return [$exitCode, $exitCode === self::SUCCESS ? null : "Import failed: could not read {$csvPath}."];
    }
}
