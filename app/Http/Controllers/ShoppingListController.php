<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShoppingList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShoppingListController extends Controller
{
    public function index(Request $request): View
    {
        $lists = $request->user()->shoppingLists()
            ->with('products')
            ->orderBy('created_at')
            ->get();

        return view('pages.cart', [
            'lists' => $lists,
            'activeListId' => $this->activeList($request)->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $list = $request->user()->shoppingLists()->create($validated);

        $request->session()->put('active_shopping_list_id', $list->id);

        return back()->with('success', 'Saraksts izveidots');
    }

    public function show(Request $request, ShoppingList $shoppingList): View
    {
        $this->authorizeList($request, $shoppingList);

        $shoppingList->load('products.latestPriceHistory');

        return view('pages.cart-show', [
            'list' => $shoppingList,
        ]);
    }

    public function update(Request $request, ShoppingList $shoppingList): RedirectResponse
    {
        $this->authorizeList($request, $shoppingList);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $shoppingList->update($validated);

        return back()->with('success', 'Saraksts pārdēvēts');
    }

    public function destroy(Request $request, ShoppingList $shoppingList): RedirectResponse
    {
        $this->authorizeList($request, $shoppingList);

        $shoppingList->delete();

        if ((int) $request->session()->get('active_shopping_list_id') === $shoppingList->id) {
            $request->session()->forget('active_shopping_list_id');
        }

        return to_route('cart');
    }

    public function activate(Request $request, ShoppingList $shoppingList): RedirectResponse
    {
        $this->authorizeList($request, $shoppingList);

        $request->session()->put('active_shopping_list_id', $shoppingList->id);

        return back()->with('success', 'Aktīvais saraksts nomainīts');
    }

    public function quickAdd(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $this->incrementItem($this->activeList($request), $product);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Produkts veiksmīgi pievienots iepirkuma sarakstam']);
        }

        return back()->with('success', 'Produkts veiksmīgi pievienots iepirkuma sarakstam');
    }

    public function increase(Request $request, ShoppingList $shoppingList, Product $product): RedirectResponse
    {
        $this->authorizeList($request, $shoppingList);

        $this->incrementItem($shoppingList, $product);

        return to_route('cart.show', $shoppingList);
    }

    public function decrease(Request $request, ShoppingList $shoppingList, Product $product): RedirectResponse
    {
        $this->authorizeList($request, $shoppingList);

        $existing = $shoppingList->products()->where('product_id', $product->id)->first();
        $quantity = ($existing?->pivot->quantity ?? 0) - 1;

        if ($quantity > 0) {
            $shoppingList->products()->updateExistingPivot($product->id, ['quantity' => $quantity]);
        } else {
            $shoppingList->products()->detach($product->id);
        }

        return to_route('cart.show', $shoppingList);
    }

    public function destroyItem(Request $request, ShoppingList $shoppingList, Product $product): RedirectResponse
    {
        $this->authorizeList($request, $shoppingList);

        $shoppingList->products()->detach($product->id);

        return to_route('cart.show', $shoppingList);
    }

    private function incrementItem(ShoppingList $list, Product $product): void
    {
        $existing = $list->products()->where('product_id', $product->id)->first();

        if ($existing) {
            $list->products()->updateExistingPivot($product->id, [
                'quantity' => $existing->pivot->quantity + 1,
            ]);
        } else {
            $list->products()->attach($product->id, ['quantity' => 1]);
        }
    }

    private function activeList(Request $request): ShoppingList
    {
        $user = $request->user();
        $activeId = $request->session()->get('active_shopping_list_id');

        $list = $activeId
            ? $user->shoppingLists()->find($activeId)
            : null;

        $list ??= $user->shoppingLists()->orderBy('created_at')->first();

        $list ??= $user->shoppingLists()->create(['name' => 'Mans saraksts']);

        $request->session()->put('active_shopping_list_id', $list->id);

        return $list;
    }

    private function authorizeList(Request $request, ShoppingList $shoppingList): void
    {
        abort_unless($shoppingList->user_id === $request->user()->id, 403);
    }
}
