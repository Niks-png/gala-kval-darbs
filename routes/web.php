<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductWatchController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ShoppingListController;
use App\Http\Controllers\ShoppingListInvitationController;
use App\Http\Controllers\ShoppingListInviteController;
use App\Http\Controllers\ShoppingListMemberController;
use App\Models\Store;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [ProductController::class, 'dashboard'])->name('dashboard');
    Route::get('products/search', [ProductController::class, 'search'])->name('products.search');
    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('price-history', [ProductController::class, 'priceHistory'])->name('price-history');

    Route::view('recipes', 'pages.recipes')->name('recipes');
    Route::post('recipes/match', [RecipeController::class, 'match'])->name('recipes.match');
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
    Route::post('cart/{shoppingList}/complete', [ShoppingListController::class, 'complete'])->name('cart.complete');
    Route::delete('cart/{shoppingList}/complete', [ShoppingListController::class, 'reopen'])->name('cart.reopen');
    Route::post('products/{product}/watch', [ProductWatchController::class, 'store'])->name('products.watch');
    Route::delete('products/{product}/watch', [ProductWatchController::class, 'destroy'])->name('products.unwatch');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::delete('notifications', [NotificationController::class, 'destroyAll'])->name('notifications.destroy-all');
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::post('invitations/{invitation}/accept', [ShoppingListInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [ShoppingListInvitationController::class, 'destroy'])->name('invitations.destroy');
    Route::get('cart/join/{token}', [ShoppingListInviteController::class, 'accept'])->name('cart.invite.accept');
    Route::post('cart/{shoppingList}/invite-link', [ShoppingListInviteController::class, 'store'])->name('cart.invite.store');
    Route::delete('cart/{shoppingList}/invite-link', [ShoppingListInviteController::class, 'destroy'])->name('cart.invite.destroy');
    Route::post('cart/{shoppingList}/members', [ShoppingListMemberController::class, 'store'])->name('cart.members.store');
    Route::patch('cart/{shoppingList}/members/{user}', [ShoppingListMemberController::class, 'update'])->name('cart.members.update');
    Route::delete('cart/{shoppingList}/members/{user}', [ShoppingListMemberController::class, 'destroy'])->name('cart.members.destroy');
    Route::post('cart/items', [ShoppingListController::class, 'storeMany'])->name('cart.items.store-many');
    Route::post('cart/items/{product}', [ShoppingListController::class, 'quickAdd'])->name('cart.items.store');
    Route::post('products/{product}/add-to-list', [ShoppingListController::class, 'storeItem'])->name('products.add-to-list');
    Route::post('cart/{shoppingList}/items/{product}', [ShoppingListController::class, 'increase'])->name('cart.lists.items.store');
    Route::post('cart/{shoppingList}/items/{product}/decrease', [ShoppingListController::class, 'decrease'])->name('cart.lists.items.decrease');
    Route::delete('cart/{shoppingList}/items/{product}', [ShoppingListController::class, 'destroyItem'])->name('cart.lists.items.destroy');
});

Route::middleware(['auth', 'verified', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('index');
    Route::post('scrape', [AdminDashboardController::class, 'scrape'])->name('scrape');
    Route::get('users', [AdminUserController::class, 'index'])->name('users');
    Route::patch('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
});

require __DIR__.'/settings.php';
