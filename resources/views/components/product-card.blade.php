@props(['product'])

@php
    $previousPrice = $product->latestPriceHistory?->previous_price;
    $showPreviousPrice = $previousPrice !== null
        && $product->current_price !== null
        && (float) $previousPrice > (float) $product->current_price;
@endphp

<article class="flex flex-col overflow-hidden rounded-xl border border-neutral-200 shadow-sm transition hover:border-emerald-500 hover:shadow-md dark:border-neutral-700">
    <a href="{{ route('products.show', $product) }}" wire:navigate class="block flex-1">
        <div class="flex h-32 items-center justify-center bg-neutral-50 dark:bg-neutral-800">
            @if ($product->image_url)
                <img src="{{ $product->image_url }}" alt="" class="h-full w-full object-contain p-2" loading="lazy">
            @else
                <flux:icon.photo class="size-8 text-neutral-300 dark:text-neutral-600" />
            @endif
        </div>
        <div class="p-4">
            <flux:heading size="sm">{{ $product->title }}</flux:heading>
            <flux:text class="mt-1">{{ $product->store }}</flux:text>
            <div class="mt-4 flex items-baseline justify-between gap-3">
                <flux:heading size="lg">
                    {{ $product->current_price !== null ? number_format((float) $product->current_price, 2) . ' €' : '—' }}
                </flux:heading>
                @if ($showPreviousPrice)
                    <flux:text class="text-neutral-500 line-through">{{ number_format((float) $previousPrice, 2) }} €</flux:text>
                @endif
                @if ($product->unit_price !== null && $product->unit !== null)
                    <flux:text>{{ number_format((float) $product->unit_price, 2) }} {{ $product->unit }}</flux:text>
                @endif
            </div>
        </div>
    </a>
    <div class="flex items-center justify-between gap-3 border-t border-neutral-200 px-4 py-2 dark:border-neutral-700">
        <a href="{{ route('products.show', $product) }}" wire:navigate class="text-sm text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300">{{ __('Sīkāk') }}</a>
        <form method="POST" action="{{ route('cart.items.store', $product) }}" class="add-to-cart-form">
            @csrf
            <button type="submit" class="flex items-center gap-1 rounded-lg px-2 py-1 text-sm font-medium text-emerald-600 transition hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950">
                <flux:icon.plus variant="micro" />
                {{ __('Pievienot sarakstam') }}
            </button>
        </form>
    </div>
</article>
