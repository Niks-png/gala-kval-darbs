<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\ShoppingList;
use App\Services\SimilarProductFinder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Price history periods a page can show: days => label.
     */
    public const HISTORY_PERIODS = [30 => '30 dienas', 90 => '3 mēneši', 365 => 'Gads'];

    private const DEFAULT_HISTORY_DAYS = 90;

    public function dashboard(Request $request): View
    {
        $filters = $this->filters($request);

        return view('dashboard', [
            ...$filters,
            ...$this->filterOptions(),
            'products' => $this->filteredQuery($filters)
                ->with(['priceHistory' => $this->cardHistory(...)])
                ->orderBy('title')
                ->paginate(30)
                ->withQueryString(),
            'productCount' => Product::query()->onOffer()->count(),
            'storeCount' => Product::query()->onOffer()->distinct('store')->count('store'),
            'recentPriceDrops' => ProductPriceHistory::query()
                ->whereColumn('new_price', '<', 'previous_price')
                ->where('created_at', '>=', now()->subWeek())
                ->count(),
            'watchedIds' => $this->watchedIds($request),
        ]);
    }

    public function search(Request $request): View
    {
        $filters = $this->filters($request);
        $hasFilters = array_filter($filters) !== [];

        return view('pages.product-search', [
            ...$filters,
            ...$this->filterOptions(),
            'products' => $hasFilters
                ? $this->filteredQuery($filters)
                    ->with(['priceHistory' => $this->cardHistory(...)])
                    ->orderBy('title')
                    ->paginate(30)
                    ->withQueryString()
                : null,
            'watchedIds' => $this->watchedIds($request),
        ]);
    }

    /**
     * IDs of products the user follows, for the bell on product cards.
     *
     * @return Collection<int, int>
     */
    private function watchedIds(Request $request): Collection
    {
        return $request->user()->watchedProducts()->pluck('products.id');
    }

    public function priceHistory(Request $request): View
    {
        $filters = $this->filters($request);
        $days = $this->historyDays($request);
        $inPeriod = fn ($history) => $history->where('created_at', '>=', now()->subDays($days));

        return view('pages.price-history', [
            ...$filters,
            ...$this->filterOptions(),
            'period' => $days,
            'products' => $this->filteredQuery($filters)
                ->whereHas('priceHistory', $inPeriod)
                ->with(['priceHistory' => fn ($history) => $inPeriod($history)->orderBy('created_at')])
                ->withMax('priceHistory', 'created_at')
                ->orderByDesc('price_history_max_created_at')
                ->orderBy('title')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function show(Request $request, Product $product, SimilarProductFinder $finder): View
    {
        $days = $this->historyDays($request);
        $product->load(['priceHistory' => fn ($history) => $history
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at')]);

        return view('pages.product-show', [
            'product' => $product,
            'period' => $days,
            'similarProducts' => $finder->find($product),
            'lists' => ShoppingList::query()
                ->editableBy($request->user())
                ->open()
                ->orderBy('name')
                ->get(),
            'activeListId' => (int) $request->session()->get('active_shopping_list_id'),
            'isWatching' => $request->user()->isWatching($product),
        ]);
    }

    /**
     * Product cards only need the last 30 days: the sparkline, the latest drop and the 30-day low.
     * Loading every change ever would grow with every day of scraping.
     */
    private function cardHistory(Relation $history): void
    {
        $history->where('created_at', '>=', now()->subDays(30));
    }

    /**
     * The price history period chosen on the page, in days.
     */
    private function historyDays(Request $request): int
    {
        $days = (int) $request->query('period', self::DEFAULT_HISTORY_DAYS);

        return array_key_exists($days, self::HISTORY_PERIODS) ? $days : self::DEFAULT_HISTORY_DAYS;
    }

    /**
     * @return array{query: string, store: string, category: string}
     */
    private function filters(Request $request): array
    {
        return [
            'query' => trim((string) $request->string('q')),
            'store' => trim((string) $request->string('store')),
            'category' => trim((string) $request->string('category')),
        ];
    }

    /**
     * @return array{stores: Collection<int, string>, categories: Collection<int, string>}
     */
    private function filterOptions(): array
    {
        return [
            'stores' => Product::query()->onOffer()->distinct()->orderBy('store')->pluck('store'),
            'categories' => Product::query()->onOffer()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ];
    }

    /**
     * @param  array{query: string, store: string, category: string}  $filters
     * @return Builder<Product>
     */
    private function filteredQuery(array $filters): Builder
    {
        return Product::query()->onOffer()
            ->when($filters['query'] !== '', fn (Builder $products) => $products->whereContains('title', $filters['query']))
            ->when($filters['store'] !== '', fn (Builder $products) => $products->where('store', $filters['store']))
            ->when($filters['category'] !== '', fn (Builder $products) => $products->where('category', $filters['category']));
    }
}
