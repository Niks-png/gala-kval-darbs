<?php

use App\Http\Controllers\ShoppingListController;
use App\Http\Controllers\ShoppingListInvitationController;
use App\Http\Controllers\ShoppingListInviteController;
use App\Http\Controllers\ShoppingListMemberController;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function (Request $request) {
        $query = trim((string) $request->string('q'));

        $products = Product::query()
            ->when($query !== '', fn ($products) => $products->where('title', 'like', "%{$query}%"))
            ->with('latestPriceHistory')
            ->orderBy('title')
            ->limit(60)
            ->get();

        return view('dashboard', [
            'products' => $products,
            'query' => $query,
            'productCount' => Product::query()->count(),
            'storeCount' => Product::query()->whereNotNull('store')->distinct('store')->count('store'),
            'recentPriceDrops' => ProductPriceHistory::query()
                ->whereColumn('new_price', '<', 'previous_price')
                ->where('created_at', '>=', now()->subWeek())
                ->count(),
        ]);
    })->name('dashboard');
    Route::view('recipes', 'pages.recipes')->name('recipes');
    Route::get('map', function () {
        return view('pages.map', [
            'stores' => Store::query()->orderBy('name')->get(),
        ]);
    })->name('map');
    Route::get('cart', [ShoppingListController::class, 'index'])->name('cart');
    Route::post('cart', [ShoppingListController::class, 'store'])->name('cart.store');
    Route::get('cart/{shoppingList}', [ShoppingListController::class, 'show'])->name('cart.show');
    Route::patch('cart/{shoppingList}', [ShoppingListController::class, 'update'])->name('cart.update');
    Route::delete('cart/{shoppingList}', [ShoppingListController::class, 'destroy'])->name('cart.destroy');
    Route::post('cart/{shoppingList}/activate', [ShoppingListController::class, 'activate'])->name('cart.activate');
    Route::get('notifications', [ShoppingListInvitationController::class, 'index'])->name('notifications');
    Route::post('invitations/{invitation}/accept', [ShoppingListInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [ShoppingListInvitationController::class, 'destroy'])->name('invitations.destroy');
    Route::get('cart/join/{token}', [ShoppingListInviteController::class, 'accept'])->name('cart.invite.accept');
    Route::post('cart/{shoppingList}/invite-link', [ShoppingListInviteController::class, 'store'])->name('cart.invite.store');
    Route::delete('cart/{shoppingList}/invite-link', [ShoppingListInviteController::class, 'destroy'])->name('cart.invite.destroy');
    Route::post('cart/{shoppingList}/members', [ShoppingListMemberController::class, 'store'])->name('cart.members.store');
    Route::patch('cart/{shoppingList}/members/{user}', [ShoppingListMemberController::class, 'update'])->name('cart.members.update');
    Route::delete('cart/{shoppingList}/members/{user}', [ShoppingListMemberController::class, 'destroy'])->name('cart.members.destroy');
    Route::get('price-history', function (Request $request) {
        $query = trim((string) $request->string('q'));
        $store = trim((string) $request->string('store'));

        $products = Product::query()
            ->whereHas('priceHistory')
            ->with(['priceHistory' => fn ($history) => $history->orderBy('created_at')])
            ->when($query !== '', fn ($products) => $products->where('title', 'like', "%{$query}%"))
            ->when($store !== '', fn ($products) => $products->where('store', $store))
            ->get()
            ->sortByDesc(fn (Product $product) => $product->priceHistory->max('created_at'))
            ->values();

        return view('pages.price-history', [
            'products' => $products,
            'query' => $query,
            'store' => $store,
            'stores' => Product::query()->whereNotNull('store')->distinct()->orderBy('store')->pluck('store'),
        ]);
    })->name('price-history');
    Route::post('cart/items/{product}', [ShoppingListController::class, 'quickAdd'])->name('cart.items.store');
    Route::post('cart/{shoppingList}/items/{product}', [ShoppingListController::class, 'increase'])->name('cart.lists.items.store');
    Route::post('cart/{shoppingList}/items/{product}/decrease', [ShoppingListController::class, 'decrease'])->name('cart.lists.items.decrease');
    Route::delete('cart/{shoppingList}/items/{product}', [ShoppingListController::class, 'destroyItem'])->name('cart.lists.items.destroy');
    Route::get('products/search', function (Request $request) {
        $query = trim((string) $request->string('q'));
        $store = trim((string) $request->string('store'));
        $category = trim((string) $request->string('category'));
        $productQuery = Product::query()
            ->when($query !== '', fn ($products) => $products->where('title', 'like', "%{$query}%"))
            ->when($store !== '', fn ($products) => $products->where('store', $store))
            ->when($category !== '', fn ($products) => $products->where('category', $category))
            ->with('latestPriceHistory')
            ->orderBy('title');
        $products = $query === '' && $store === '' && $category === ''
            ? collect()
            : $productQuery->get();

        return view('pages.product-search', [
            'query' => $query,
            'store' => $store,
            'category' => $category,
            'stores' => Product::query()->whereNotNull('store')->distinct()->orderBy('store')->pluck('store'),
            'categories' => Product::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'products' => $products,
        ]);
    })->name('products.search');
});

require __DIR__.'/settings.php';
