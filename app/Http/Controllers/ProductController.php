<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\ShoppingList;
use App\Services\SimilarProductFinder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function dashboard(Request $request): View
    {
        $filters = $this->filters($request);

        return view('dashboard', [
            ...$filters,
            ...$this->filterOptions(),
            'products' => $this->filteredQuery($filters)
                ->with('priceHistory')
                ->orderBy('title')
                ->paginate(30)
                ->withQueryString(),
            'productCount' => Product::query()->count(),
            'storeCount' => Product::query()->whereNotNull('store')->distinct('store')->count('store'),
            'recentPriceDrops' => ProductPriceHistory::query()
                ->whereColumn('new_price', '<', 'previous_price')
                ->where('created_at', '>=', now()->subWeek())
                ->count(),
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
                    ->with('priceHistory')
                    ->orderBy('title')
                    ->paginate(30)
                    ->withQueryString()
                : null,
        ]);
    }

    public function priceHistory(Request $request): View
    {
        $filters = $this->filters($request);

        return view('pages.price-history', [
            ...$filters,
            ...$this->filterOptions(),
            'products' => $this->filteredQuery($filters)
                ->whereHas('priceHistory')
                ->with(['priceHistory' => fn ($history) => $history->orderBy('created_at')])
                ->withMax('priceHistory', 'created_at')
                ->orderByDesc('price_history_max_created_at')
                ->orderBy('title')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function show(Request $request, Product $product, SimilarProductFinder $finder): View
    {
        $product->load(['priceHistory' => fn ($history) => $history->orderBy('created_at')]);

        return view('pages.product-show', [
            'product' => $product,
            'similarProducts' => $finder->find($product),
            'lists' => ShoppingList::query()
                ->editableBy($request->user())
                ->open()
                ->orderBy('name')
                ->get(),
            'activeListId' => (int) $request->session()->get('active_shopping_list_id'),
        ]);
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
            'stores' => Product::query()->whereNotNull('store')->distinct()->orderBy('store')->pluck('store'),
            'categories' => Product::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ];
    }

    /**
     * @param  array{query: string, store: string, category: string}  $filters
     * @return Builder<Product>
     */
    private function filteredQuery(array $filters): Builder
    {
        return Product::query()
            ->when($filters['query'] !== '', fn (Builder $products) => $products->where('title', 'like', "%{$filters['query']}%"))
            ->when($filters['store'] !== '', fn (Builder $products) => $products->where('store', $filters['store']))
            ->when($filters['category'] !== '', fn (Builder $products) => $products->where('category', $filters['category']));
    }
}
