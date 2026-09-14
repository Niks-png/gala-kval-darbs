<?php

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $products = Product::query()
            ->with('latestPriceHistory')
            ->orderBy('title')
            ->limit(60)
            ->get();

        return view('dashboard', compact('products'));
    })->name('dashboard');
    Route::view('recipes', 'pages.recipes')->name('recipes');
    Route::view('map', 'pages.map')->name('map');
    Route::get('cart', function (Request $request) {
        $cart = $request->session()->get('cart', []);
        $products = Product::query()->whereIn('id', array_keys($cart))->get();

        return view('pages.cart', [
            'cart' => $cart,
            'products' => $products,
        ]);
    })->name('cart');
    Route::get('price-history', function () {
        $history = \App\Models\ProductPriceHistory::query()
            ->with('product')
            ->latest()
            ->get();

        return view('pages.price-history', compact('history'));
    })->name('price-history');
    Route::post('cart/items/{product}', function (Request $request, Product $product) {
        $cart = $request->session()->get('cart', []);
        $cart[$product->id] = ($cart[$product->id] ?? 0) + 1;
        $request->session()->put('cart', $cart);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Produkts veiksmīgi pievienots iepirkuma sarakstam']);
        }

        return back()->with('success', 'Produkts veiksmīgi pievienots iepirkuma sarakstam');
    })->name('cart.items.store');
    Route::post('cart/items/{product}/decrease', function (Request $request, Product $product) {
        $cart = $request->session()->get('cart', []);
        $quantity = ($cart[$product->id] ?? 0) - 1;

        if ($quantity > 0) {
            $cart[$product->id] = $quantity;
        } else {
            unset($cart[$product->id]);
        }

        $request->session()->put('cart', $cart);

        return to_route('cart');
    })->name('cart.items.decrease');
    Route::delete('cart/items/{product}', function (Request $request, Product $product) {
        $cart = $request->session()->get('cart', []);
        unset($cart[$product->id]);
        $request->session()->put('cart', $cart);

        return to_route('cart');
    })->name('cart.items.destroy');
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
