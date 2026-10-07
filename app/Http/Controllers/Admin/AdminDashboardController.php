<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\ScrapeProductsCommand;
use App\Http\Controllers\Controller;
use App\Jobs\RunScrape;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\ScrapeRun;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $products = Product::query()->onOffer()
            ->selectRaw('store, count(*) as products, sum(original_price is not null) as discounted, max(updated_at) as last_updated')
            ->groupBy('store')
            ->get()
            ->keyBy('store');

        $latestRuns = ScrapeRun::query()
            ->whereIn('id', ScrapeRun::query()->selectRaw('max(id)')->groupBy('store'))
            ->get()
            ->keyBy('store');

        // Scraper key ("rimi") => store value in the products table ("rimi.lv").
        $stores = collect(ScrapeProductsCommand::enabledStores())->map(function (string $key) use ($products, $latestRuns): array {
            $domain = ScrapeProductsCommand::STORE_DOMAINS[$key];
            $stats = $products->get($domain);

            return [
                'key' => $key,
                'domain' => $domain,
                'products' => (int) ($stats->products ?? 0),
                'discounted' => (int) ($stats->discounted ?? 0),
                'lastUpdated' => isset($stats->last_updated) ? Date::parse($stats->last_updated) : null,
                'lastRun' => $latestRuns->get($key),
            ];
        });

        return view('pages.admin.index', [
            'stores' => $stores,
            'totals' => [
                'users' => User::query()->count(),
                'products' => $products->sum('products'),
                'lists' => ShoppingList::query()->count(),
                'priceChanges' => ProductPriceHistory::query()->where('created_at', '>=', now()->subDay())->count(),
            ],
            'recentRuns' => ScrapeRun::query()->latest('started_at')->latest('id')->limit(15)->get(),
            'pendingScrapes' => $this->pendingScrapes(),
            'log' => $this->logTail(),
        ]);
    }

    public function scrape(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store' => ['nullable', Rule::in(ScrapeProductsCommand::enabledStores())],
        ]);

        RunScrape::dispatch(isset($validated['store']) ? [$validated['store']] : []);

        return back()->with('success', isset($validated['store'])
            ? __('Cenu atjaunošana veikalam :store ielikta rindā.', ['store' => $validated['store']])
            : __('Cenu atjaunošana visiem veikaliem ielikta rindā.'));
    }

    /**
     * Admin-started scrapes still waiting for a queue worker.
     */
    private function pendingScrapes(): int
    {
        if (config('queue.default') !== 'database') {
            return 0;
        }

        return DB::table(config('queue.connections.database.table', 'jobs'))
            ->where('payload', 'like', '%RunScrape%')
            ->count();
    }

    /**
     * Last lines of the scrape log written by the scheduler and RunScrape.
     */
    private function logTail(int $lines = 40): ?string
    {
        $path = storage_path('logs/scrape.log');

        if (! is_file($path)) {
            return null;
        }

        // Only the end of the file; the log grows every day.
        $content = file_get_contents($path, offset: max(0, (int) filesize($path) - 16384));

        if ($content === false || trim($content) === '') {
            return null;
        }

        $logLines = explode("\n", str_replace("\r\n", "\n", rtrim($content)));

        return implode("\n", array_slice($logLines, -$lines));
    }
}
