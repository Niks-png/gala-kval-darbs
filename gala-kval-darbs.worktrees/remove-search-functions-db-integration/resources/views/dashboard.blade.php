<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div>
            <flux:heading size="xl">{{ __('Products') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Browse products from all available stores.') }}</flux:text>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($products as $product)
                <form method="POST" action="{{ route('cart.items.store', $product) }}" class="add-to-cart-form">
                    @csrf
                    <button type="submit" class="block w-full rounded-xl border border-neutral-200 p-4 text-start transition hover:border-emerald-500 hover:shadow-md dark:border-neutral-700">
                        <flux:heading size="sm">{{ $product->title }}</flux:heading>
                        <flux:text class="mt-1">{{ $product->store }}</flux:text>
                        <div class="mt-4 flex items-baseline justify-between gap-3">
                            <flux:heading size="lg">
                                {{ $product->current_price !== null ? number_format((float) $product->current_price, 2) . ' €' : '—' }}
                            </flux:heading>
                            @if ($product->unit_price !== null && $product->unit !== null)
                                <flux:text>{{ number_format((float) $product->unit_price, 2) }} {{ $product->unit }}</flux:text>
                            @endif
                        </div>
                        <flux:text class="mt-3 text-emerald-600 dark:text-emerald-400">{{ __('Add to cart') }}</flux:text>
                    </button>
                </form>
            @endforeach
        </div>
    </div>
</x-layouts::app>
