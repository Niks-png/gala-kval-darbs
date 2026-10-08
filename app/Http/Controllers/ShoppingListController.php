<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShoppingList;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class ShoppingListController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Counts and totals come from the database (withItemTotals), not from loading every product.
        $lists = $user->shoppingLists()
            ->open()
            ->withItemTotals()
            ->withCount('members')
            ->orderBy('created_at')
            ->get();

        $sharedLists = $user->sharedShoppingLists()
            ->open()
            ->withItemTotals()
            ->with('user')
            ->orderBy('shopping_lists.created_at')
            ->get();

        // History grows forever, so it is paged.
        $completedLists = ShoppingList::query()
            ->visibleTo($user)
            ->completed()
            ->withItemTotals()
            ->with('user')
            ->orderByDesc('completed_at')
            ->paginate(10, pageName: 'history');

        return view('pages.cart', [
            'lists' => $lists,
            'sharedLists' => $sharedLists,
            'completedLists' => $completedLists,
            'monthlySpending' => $this->monthlySpending($user),
            // Only looked up: viewing the page must not create a list.
            'activeListId' => $this->findActiveList($request)?->id,
        ]);
    }

    public function complete(ShoppingList $shoppingList): RedirectResponse
    {
        Gate::authorize('complete', $shoppingList);

        if ($shoppingList->isCompleted()) {
            return to_route('cart.show', $shoppingList);
        }

        if (! $shoppingList->complete()) {
            // Either nothing was ticked, or another editor finished the list a moment ago.
            return to_route('cart.show', $shoppingList)->with('error', $shoppingList->fresh()->isCompleted()
                ? __('Šo sarakstu jau pabeidza cits dalībnieks.')
                : __('Atzīmē nopirktās preces, pirms pabeidz iepirkšanos.'));
        }

        return to_route('cart.show', $shoppingList)
            ->with('success', __('Iepirkšanās pabeigta. Nopirkto preču summa pēc veikala cenām: :total €', ['total' => lv_number((float) $shoppingList->completed_total, 2)]));
    }

    /**
     * Buy the same things again: a new list with the finished list's items. The finished
     * list is history and is never reopened or changed.
     */
    public function copy(Request $request, ShoppingList $shoppingList): RedirectResponse
    {
        Gate::authorize('view', $shoppingList);

        $copy = $shoppingList->copyFor($request->user());
        $request->session()->put('active_shopping_list_id', $copy->id);

        return to_route('cart.show', $copy)->with('success', __('Izveidots jauns saraksts ar tām pašām precēm'));
    }

    /**
     * Add a product to a chosen list (used by the product page).
     */
    public function storeItem(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'shopping_list_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.ShoppingList::MAX_QUANTITY],
        ]);

        $list = ShoppingList::query()->findOrFail($validated['shopping_list_id']);

        Gate::authorize('editItems', $list);

        if (! $list->addProduct($product->id, (int) $validated['quantity'])) {
            return back()->with('error', __('Saraksts jau ir pabeigts.'));
        }

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

        return back()->with('success', __('Saraksts izveidots'));
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

        return back()->with('success', __('Saraksts pārdēvēts'));
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

        return back()->with('success', __('Aktīvais saraksts nomainīts'));
    }

    public function quickAdd(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $this->addToActiveList($request, [$product->id]);

        if ($request->expectsJson()) {
            return response()->json(['message' => __('Produkts veiksmīgi pievienots iepirkuma sarakstam')]);
        }

        return back()->with('success', __('Produkts veiksmīgi pievienots iepirkuma sarakstam'));
    }

    public function increase(Request $request, ShoppingList $shoppingList, Product $product): RedirectResponse
    {
        Gate::authorize('editItems', $shoppingList);

        return $this->afterItemChange($shoppingList, $shoppingList->addProduct($product->id));
    }

    public function decrease(Request $request, ShoppingList $shoppingList, Product $product): RedirectResponse
    {
        Gate::authorize('editItems', $shoppingList);

        return $this->afterItemChange($shoppingList, $shoppingList->decreaseProduct($product->id));
    }

    public function destroyItem(Request $request, ShoppingList $shoppingList, Product $product): RedirectResponse
    {
        Gate::authorize('editItems', $shoppingList);

        return $this->afterItemChange($shoppingList, $shoppingList->removeProduct($product->id));
    }

    /**
     * Add several products at once to the active list (used by the recipe page).
     * All of them or none: a recipe should not end up half on the list.
     */
    public function storeMany(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'max:50'],
            // Must be a current offer with a price, not just any product that ever existed.
            'product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')->whereNull('offer_ended_at')->whereNotNull('current_price')],
        ]);

        $list = $this->addToActiveList($request, array_map('intval', $validated['product_ids']));

        return response()->json([
            'message' => trans_choice(
                '{1} :count produkts pievienots sarakstam ":list"|[2,*] :count produkti pievienoti sarakstam ":list"',
                count($validated['product_ids']),
                ['list' => $list->name],
            ),
            'url' => route('cart.show', $list),
        ]);
    }

    private function afterItemChange(ShoppingList $list, bool $changed): RedirectResponse
    {
        return $changed
            ? to_route('cart.show', $list)
            : to_route('cart.show', $list)->with('error', __('Saraksts jau ir pabeigts, to vairs nevar mainīt.'));
    }

    /**
     * Add products to the user's active list in one transaction, creating "Mans saraksts"
     * only if the user has no list they can add to.
     *
     * @param  list<int>  $productIds
     */
    private function addToActiveList(Request $request, array $productIds): ShoppingList
    {
        return DB::transaction(function () use ($request, $productIds): ShoppingList {
            // Lock the user's row so two requests at once cannot both create a default list.
            User::query()->lockForUpdate()->find($request->user()->id);

            $list = $this->findActiveList($request) ?? $request->user()->shoppingLists()->create(['name' => __('Mans saraksts')]);

            foreach ($productIds as $productId) {
                if (! $list->addProduct($productId)) {
                    throw new RuntimeException("Shopping list {$list->id} was finished while products were being added.");
                }
            }

            $request->session()->put('active_shopping_list_id', $list->id);

            return $list;
        });
    }

    /**
     * The open list new products go to: the one chosen in this session, else the user's
     * oldest own list, else the oldest shared list they may edit. Never creates one.
     */
    private function findActiveList(Request $request): ?ShoppingList
    {
        $user = $request->user();
        $activeId = $request->session()->get('active_shopping_list_id');

        $list = $activeId
            ? ShoppingList::query()->editableBy($user)->open()->find($activeId)
            : null;

        return $list
            ?? $user->shoppingLists()->open()->orderBy('created_at')->first()
            ?? ShoppingList::query()->editableBy($user)->open()->orderBy('created_at')->first();
    }

    /**
     * Money spent on finished lists in each of the last six months, oldest first.
     * Reads only those six months (two columns), not the whole history.
     *
     * @return Collection<int, array{label: string, total: float}>
     */
    private function monthlySpending(User $user): Collection
    {
        // Months as users see them: in Riga time, not UTC (a purchase at 01:30 on 1 October in
        // Riga is still 30 September in UTC).
        $timezone = config('app.display_timezone');

        $totals = ShoppingList::query()
            ->visibleTo($user)
            ->where('completed_at', '>=', now($timezone)->startOfMonth()->subMonths(5)->utc())
            ->toBase()
            ->get(['completed_at', 'completed_total'])
            ->groupBy(fn (object $list): string => CarbonImmutable::parse($list->completed_at, 'UTC')->local()->format('Y-m'))
            ->map(fn (Collection $lists): float => (float) $lists->sum('completed_total'));

        return collect(range(5, 0))->map(function (int $monthsAgo) use ($totals, $timezone): array {
            $month = now($timezone)->startOfMonth()->subMonths($monthsAgo);

            return [
                'label' => $month->translatedFormat('F'),
                'total' => $totals->get($month->format('Y-m'), 0.0),
            ];
        });
    }
}
