<?php

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('recipes', 'pages.recipes')->name('recipes');
    Route::view('cart', 'pages.cart')->name('cart');
    Route::get('products/search', function (Request $request) {
        $query = trim((string) $request->string('q'));
        $products = $query === ''
            ? collect()
            : Product::query()
                ->where('title', 'like', "%{$query}%")
                ->orderBy('title')
                ->get();

        return view('pages.product-search', [
            'query' => $query,
            'products' => $products,
        ]);
    })->name('products.search');
});

require __DIR__.'/settings.php';
