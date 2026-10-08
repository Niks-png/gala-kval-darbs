<?php

use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Items of a shopping list. Polls so every member sees the others' changes.
 */
new class extends Component {
    public ShoppingList $list;

    public function hydrate(): void
    {
        if (Gate::denies('view', $this->list)) {
            $this->redirectRoute('cart');
        }
    }

    public function toggle(int $productId): void
    {
        Gate::authorize('editItems', $this->list);

        $this->list->toggleProductChecked($productId, Auth::user());
    }

    public function increase(int $productId): void
    {
        Gate::authorize('editItems', $this->list);

        if ($this->list->products()->whereKey($productId)->exists()) {
            $this->list->addProduct($productId);
        }
    }

    public function decrease(int $productId): void
    {
        Gate::authorize('editItems', $this->list);

        $this->list->decreaseProduct($productId);
    }

    public function remove(int $productId): void
    {
        Gate::authorize('editItems', $this->list);

        $this->list->removeProduct($productId);
    }

    #[Computed]
    public function canEdit(): bool
    {
        return Gate::allows('editItems', $this->list);
    }

    #[Computed]
    public function products(): Collection
    {
        return $this->list->products()
            ->orderByRaw('shopping_list_items.checked_at is not null')
            ->orderBy('shopping_list_items.id')
            ->get();
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function checkerNames(): Collection
    {
        $ids = $this->products->pluck('pivot.checked_by')->filter()->unique();

        return User::query()->whereKey($ids)->pluck('name', 'id');
    }
}; ?>

@php
    $products = $this->products;
    $completed = $this->list->isCompleted();
    // A finished list shows the prices saved when it was finished, not today's.
    $lineTotal = fn ($product) => (ShoppingList::itemPrice($product) ?? 0) * $product->pivot->quantity;
    $checkedCount = $products->whereNotNull('pivot.checked_at')->count();
    $total = $products->sum($lineTotal);
    $remaining = $products->whereNull('pivot.checked_at')->sum($lineTotal);
@endphp

{{-- A finished list cannot change, so only an open one needs to watch for other members' edits. --}}
<div @unless ($completed) wire:poll.3s @endunless>
    @if ($products->isEmpty())
        <flux:text class="mt-2">{{ __('Šis saraksts ir tukšs.') }}</flux:text>
    @else
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-neutral-50 px-4 py-3 text-sm dark:bg-neutral-800/60">
            <span>{{ __('Nopirkts :checked no :count', ['checked' => $checkedCount, 'count' => $products->count()]) }}</span>
            @if ($completed)
                <span>{{ __('Nopirkto preču summa pēc veikala cenām :date:', ['date' => $this->list->completed_at->format('d.m.Y')]) }} <strong>{{ lv_number((float) $this->list->completed_total, 2) }} €</strong></span>
            @else
                <span class="flex flex-wrap gap-4">
                    <span>{{ __('Kopā:') }} <strong>{{ lv_number($total, 2) }} €</strong></span>
                    <span>{{ __('Vēl jāpērk:') }} <strong class="text-emerald-600 dark:text-emerald-400">{{ lv_number($remaining, 2) }} €</strong></span>
                </span>
                <span class="flex items-center gap-1.5 text-xs text-neutral-500" title="{{ __('Izmaiņas, ko veic citi dalībnieki, parādās automātiski') }}">
                    <span class="size-2 animate-pulse rounded-full bg-emerald-500"></span>
                    {{ __('Tiešsaistē') }}
                </span>
            @endif
        </div>

        <div class="mt-3 space-y-3">
            @foreach ($products as $product)
                @php($checked = $product->pivot->checked_at !== null)
                <div wire:key="item-{{ $product->id }}" @class([
                    'flex items-center justify-between gap-3 rounded-xl border border-neutral-200 p-4 shadow-sm transition dark:border-neutral-700',
                    'opacity-60' => $checked,
                ])>
                    <div class="flex min-w-0 items-center gap-3">
                        @if ($this->canEdit)
                            <button type="button" wire:click="toggle({{ $product->id }})"
                                @class([
                                    'flex size-6 shrink-0 items-center justify-center rounded-md border-2 transition',
                                    'border-emerald-400 bg-emerald-400 text-ink' => $checked,
                                    'border-neutral-300 hover:border-emerald-500 dark:border-neutral-600' => ! $checked,
                                ])
                                role="checkbox" aria-checked="{{ $checked ? 'true' : 'false' }}"
                                aria-label="{{ $checked ? __('Atzīmēt kā nenopirktu') : __('Atzīmēt kā nopirktu') }}">
                                @if ($checked)
                                    <flux:icon.check variant="micro" />
                                @endif
                            </button>
                        @elseif ($checked)
                            <flux:icon.check-circle variant="solid" class="size-6 shrink-0 text-emerald-600" />
                        @endif
                        <div class="min-w-0">
                            <flux:heading size="sm" @class(['line-through' => $checked])><a href="{{ route('products.show', $product) }}" wire:navigate class="hover:text-emerald-600">{{ $product->title }}</a></flux:heading>
                            <flux:text>
                                {{ $product->store }}
                                @if ($product->offerHasEnded() && ! $checked && ! $completed)
                                    · <span class="text-amber-600 dark:text-amber-400">{{ __('piedāvājums beidzies') }}</span>
                                @endif
                                @if ($checked && isset($this->checkerNames[$product->pivot->checked_by]))
                                    · {{ __('nopirka :name', ['name' => $this->checkerNames[$product->pivot->checked_by]]) }}
                                @endif
                            </flux:text>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        @if ($this->canEdit)
                            <button type="button" wire:click="decrease({{ $product->id }})" class="flex size-8 items-center justify-center rounded-full border border-neutral-300 text-lg transition hover:border-emerald-500 hover:text-emerald-600 dark:border-neutral-600" aria-label="{{ __('Samazināt daudzumu') }}">-</button>
                        @endif
                        <span class="min-w-6 text-center">{{ $this->canEdit ? '' : '× ' }}{{ $product->pivot->quantity }}</span>
                        @if ($this->canEdit)
                            <button type="button" wire:click="increase({{ $product->id }})" class="flex size-8 items-center justify-center rounded-full border border-neutral-300 text-lg transition hover:border-emerald-500 hover:text-emerald-600 dark:border-neutral-600" aria-label="{{ __('Palielināt daudzumu') }}">+</button>
                        @endif
                        <flux:heading size="sm">
                            @if ($completed && ! $checked)
                                <span class="text-sm font-normal text-neutral-500">{{ __('nav nopirkts') }}</span>
                            @else
                                {{ ShoppingList::itemPrice($product) !== null ? lv_number($lineTotal($product), 2) . ' €' : '—' }}
                            @endif
                        </flux:heading>
                        @if ($this->canEdit)
                            <button type="button" wire:click="remove({{ $product->id }})" class="text-sm text-red-600 transition hover:text-red-700" aria-label="{{ __('Noņemt preci') }}">{{ __('Noņemt') }}</button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
