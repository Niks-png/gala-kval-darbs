<?php

namespace App\Console\Commands;

use App\Models\ScrapeRun;
use Illuminate\Console\Command;

/**
 * Keeps the price update records from growing forever. Runs daily from the scheduler.
 */
class ScrapeHousekeepingCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'products:housekeeping
        {--keep-runs=90 : Days of scrape run history to keep}
        {--max-log-kb=1024 : Size scrape.log is trimmed to}';

    /**
     * @var string
     */
    protected $description = 'Fail stuck scrape runs, drop old run history, trim scrape.log and remove leftover CSV files';

    public function handle(): int
    {
        $stale = ScrapeRun::failStaleRuns();

        $deleted = ScrapeRun::query()
            ->where('started_at', '<', now()->subDays((int) $this->option('keep-runs')))
            ->delete();

        $trimmed = $this->trimLog(storage_path('logs/scrape.log'), (int) $this->option('max-log-kb') * 1024);
        $leftovers = $this->removeLeftoverFiles();

        $this->info(sprintf(
            'Marked %d stuck runs as failed, deleted %d old runs, %s scrape.log, removed %d leftover CSV files.',
            $stale, $deleted, $trimmed ? 'trimmed' : 'kept', $leftovers,
        ));

        return self::SUCCESS;
    }

    /**
     * Keep only the newest $maxBytes of the log, starting at a whole line.
     */
    private function trimLog(string $path, int $maxBytes): bool
    {
        if (! is_file($path) || filesize($path) <= $maxBytes) {
            return false;
        }

        $tail = (string) file_get_contents($path, offset: filesize($path) - $maxBytes);
        $tail = substr($tail, (int) strpos($tail, "\n") + 1);

        file_put_contents($path, $tail, LOCK_EX);

        return true;
    }

    /**
     * Runs delete their CSV when they finish; a file older than a day was left by a crash.
     */
    private function removeLeftoverFiles(): int
    {
        $removed = 0;

        foreach (glob(rtrim(config('services.scraper.output_dir'), '\\/').'/*_products_*.csv') ?: [] as $file) {
            if (filemtime($file) < now()->subDay()->getTimestamp() && unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }
}
