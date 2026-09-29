<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShoppingList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShoppingListController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $activeListId = $this->activeList($request)->id;

        $lists = $user->shoppingLists()
            ->open()
            ->with('products')
            ->withCount('members')
            ->orderBy('created_at')
            ->get();

        $sharedLists = $user->sharedShoppingLists()
            ->open()
            ->with(['products', 'user'])
            ->orderBy('shopping_lists.created_at')
            ->get();

        $completedLists = ShoppingList::query()
            ->visibleTo($user)
            ->completed()
            ->with(['products', 'user'])
            ->orderByDesc('completed_at')
            ->get();

        return view('pages.cart', [
            'lists' => $lists,
            'sharedLists' => $sharedLists,
            'completedLists' => $completedLists,
            'monthlySpending' => $this->monthlySpending($completedLists),
            'activeListId' => $activeListId,
        ]);
    }

    public function complete(ShoppingList $shoppingList): RedirectResponse
    {
        Gate::authorize('complete', $shoppingList);

        if (! $shoppingList->isCompleted()) {
            $shoppingList->complete();
        }

        return to_route('cart.show', $shoppingList)
            ->with('success', __('Iepirkšanās pabeigta. Iztērēti :total €', ['total' => number_format((float) $shoppingList->completed_total, 2)]));
    }

    public function reopen(ShoppingList $shoppingList): RedirectResponse
    {
        Gate::authorize('complete', $shoppingList);

        $shoppingList->reopen();

        return to_route('cart.show', $shoppingList)->with('success', __('Saraksts atvērts no jauna'));
    }

    /**
     * Add a product to a chosen list (used by the product page).
     */
    public function storeItem(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'shopping_list_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $list = ShoppingList::query()->findOrFail($validated['shopping_list_id']);

        Gate::authorize('editItems', $list);

        $list->addProduct($product->id, (int) $validated['quantity']);
        $request->session()->put('active_shopping_list_id', $list->id);

        return back()->with('success', __('Produkts pievienots sarakstam ":list"', ['list' => $list->name]));
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
        Gate::authorize('view', $shoppingList);

        $shoppingList->load(['user', 'members', 'invitations.user']);

        return view('pages.cart-show', [
            'list' => $shoppingList,
            'role' => $shoppingList->roleFor($request->user()),
        ]);
    }

    public function update(Request $request, ShoppingList $shoppingList): RedirectResponse
    {
        Gate::authorize('manage', $shoppingList);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $shoppingList->update($validated);

        return back()->with('success', 'Saraksts pārdēvēts');
    }

    public function destroy(Request $request, ShoppingList $shoppingList): RedirectResponse
    {
        Gate::authorize('manage', $shoppingList);

        $shoppingList->delete();

        if ((int) $request->session()->get('active_shopping_list_id') === $shoppingList->id) {
            $request->session()->forget('active_shopping_list_id');
        }

        return to_route('cart');
    }

    public function activate(Request $request, ShoppingList $shoppingList): RedirectResponse
    {
        Gate::authorize('editItems', $shoppingList);

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
        Gate::authorize('editItems', $shoppingList);

        $this->incrementItem($shoppingList, $product);

        return to_route('cart.show', $shoppingList);
    }

    public function decrease(Request $request, ShoppingList $shoppingList, Product $product): RedirectResponse
    {
        Gate::authorize('editItems', $shoppingList);

        $shoppingList->decreaseProduct($product->id);

        return to_route('cart.show', $shoppingList);
    }

    public function destroyItem(Request $request, ShoppingList $shoppingList, Product $product): RedirectResponse
    {
        Gate::authorize('editItems', $shoppingList);

        $shoppingList->products()->detach($product->id);

        return to_route('cart.show', $shoppingList);
    }

    /**
     * Add several products at once to the active list (used by the recipe page).
     */
    public function storeMany(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'max:50'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
        ]);

        $list = $this->activeList($request);

        foreach ($validated['product_ids'] as $productId) {
            $list->addProduct((int) $productId);
        }

        return response()->json([
            'message' => trans_choice(
                '{1} :count produkts pievienots sarakstam ":list"|[2,*] :count produkti pievienoti sarakstam ":list"',
                count($validated['product_ids']),
                ['list' => $list->name],
            ),
            'url' => route('cart.show', $list),
        ]);
    }

    private function incrementItem(ShoppingList $list, Product $product): void
    {
        $list->addProduct($product->id);
    }

    private function activeList(Request $request): ShoppingList
    {
        $user = $request->user();
        $activeId = $request->session()->get('active_shopping_list_id');

        $list = $activeId
            ? ShoppingList::query()->editableBy($user)->open()->find($activeId)
            : null;

        $list ??= $user->shoppingLists()->open()->orderBy('created_at')->first();

        $list ??= $user->shoppingLists()->create(['name' => 'Mans saraksts']);

        $request->session()->put('active_shopping_list_id', $list->id);

        return $list;
    }

    /**
     * Money spent on finished lists in each of the last six months, oldest first.
     *
     * @param  Collection<int, ShoppingList>  $completedLists
     * @return Collection<int, array{label: string, total: float}>
     */
    private function monthlySpending(Collection $completedLists): Collection
    {
        $totals = $completedLists
            ->groupBy(fn (ShoppingList $list): string => $list->completed_at->format('Y-m'))
            ->map(fn (Collection $lists): float => (float) $lists->sum('completed_total'));

        return collect(range(5, 0))->map(function (int $monthsAgo) use ($totals): array {
            $month = now()->startOfMonth()->subMonths($monthsAgo);

            return [
                'label' => $month->translatedFormat('F'),
                'total' => $totals->get($month->format('Y-m'), 0.0),
            ];
        });
    }
}
