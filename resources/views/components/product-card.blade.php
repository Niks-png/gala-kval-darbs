@props(['product', 'watched' => false])

@php
    $euro = fn ($value) => lv_number($value).' €';

    $points = $product->pricePoints();
    $dropPercent = $product->latestDropPercent();
    $previousPrice = $dropPercent !== null ? $product->priceHistory->sortBy('created_at')->last()->previous_price : null;
    $isLowest = $product->isLowestPriceInDays(30);

    $trend = count($points) > 1 && end($points) !== $points[count($points) - 2]
        ? (end($points) < $points[count($points) - 2] ? 'down' : 'up')
        : 'flat';
@endphp

<article class="flex flex-col overflow-hidden rounded-xl border border-neutral-200 shadow-sm transition hover:border-emerald-700 hover:shadow-md dark:border-neutral-700 dark:bg-neutral-900">
    <a href="{{ route('products.show', $product) }}" wire:navigate class="flex flex-1 flex-col">
        <div class="flex h-32 items-center justify-center bg-neutral-50 dark:bg-neutral-800">
            @if ($product->image_url)
                <img src="{{ $product->image_url }}" alt="" class="h-full w-full object-contain p-2" loading="lazy">
            @else
                <flux:icon.photo class="size-8 text-neutral-300 dark:text-neutral-600" />
            @endif
        </div>

        <div class="flex flex-1 flex-col p-4">
            <flux:heading size="sm">{{ $product->title }}</flux:heading>
            <flux:text class="mt-1">{{ $product->store }}</flux:text>

            <div class="mt-4 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                <span class="text-2xl font-bold tabular-nums text-zinc-900 dark:text-zinc-100">
                    {{ $product->current_price !== null ? $euro($product->current_price) : '—' }}
                </span>
                @if ($previousPrice !== null)
                    <span class="font-mono text-sm tabular-nums text-zinc-500 line-through">{{ lv_number((float) $previousPrice, 2) }}</span>
                    <span class="self-center rounded-full bg-green-400/15 px-2 py-0.5 font-mono text-xs font-semibold text-green-400">−{{ $dropPercent }}%</span>
                @endif
            </div>

            @if (($product->unit_price !== null && $product->unit !== null) || $isLowest)
                <p class="mt-1 font-mono text-xs tabular-nums text-zinc-400">
                    @if ($product->unit_price !== null && $product->unit !== null)
                        {{ lv_number((float) $product->unit_price, 2) }} {{ $product->unit }}
                    @endif
                    @if ($product->unit_price !== null && $product->unit !== null && $isLowest)
                        <span aria-hidden="true">·</span>
                    @endif
                    @if ($isLowest)
                        <span class="text-green-400">{{ __('30 d. zemākā') }}</span>
                    @endif
                </p>
            @endif

            <div class="mt-auto pt-4">
                <x-price-sparkline :points="$points" :trend="$trend" area class="h-12 w-full overflow-visible" />
            </div>
        </div>
    </a>

    <div class="flex items-center justify-between gap-3 border-t border-neutral-200 px-4 py-2 dark:border-neutral-700">
        <a href="{{ route('products.show', $product) }}" wire:navigate class="text-sm text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300">{{ __('Sīkāk') }}</a>
        <div class="flex items-center gap-1">
            {{-- Follow toggle; resources/js/app.js submits it without a reload --}}
            <form method="POST" action="{{ route('products.watch', $product) }}" class="watch-form group" @if ($watched) data-watching @endif
                data-label-watch="{{ __('Sekot cenai') }}" data-label-unwatch="{{ __('Pārtraukt sekot cenai') }}">
                @csrf
                <input type="hidden" name="_method" value="{{ $watched ? 'DELETE' : 'POST' }}">
                <button type="submit"
                    class="flex size-8 items-center justify-center rounded-lg text-zinc-400 transition hover:bg-emerald-50 hover:text-emerald-700 group-data-watching:text-emerald-700 dark:hover:bg-emerald-950 dark:hover:text-emerald-400 dark:group-data-watching:text-emerald-400"
                    aria-pressed="{{ $watched ? 'true' : 'false' }}"
                    aria-label="{{ $watched ? __('Pārtraukt sekot cenai') : __('Sekot cenai') }}"
                    title="{{ $watched ? __('Pārtraukt sekot cenai') : __('Sekot cenai') }}"
                    data-test="card-watch-button">
                    <flux:icon.bell variant="mini" class="size-4 group-data-watching:hidden" />
                    <flux:icon.bell-alert variant="mini" class="hidden size-4 group-data-watching:block" />
                </button>
            </form>
            <form method="POST" action="{{ route('cart.items.store', $product) }}" class="add-to-cart-form">
                @csrf
                <button type="submit" class="flex items-center gap-1 rounded-lg px-2 py-1 text-sm font-medium text-emerald-600 transition hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950">
                    <flux:icon.plus variant="micro" />
                    {{ __('Pievienot sarakstam') }}
                </button>
            </form>
        </div>
    </div>
</article>
