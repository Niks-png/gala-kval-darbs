<x-layouts::app :title="__('Product search')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div>
            <flux:heading size="xl">{{ __('Product search') }}</flux:heading>
            @if ($query !== '')
                <flux:text class="mt-2">
                    {{ __('Results for') }} "{{ $query }}"
                </flux:text>
            @endif
        </div>

        @if ($query === '')
            <flux:text>{{ __('Enter a product name to search.') }}</flux:text>
        @elseif ($products->isEmpty())
            <flux:text>{{ __('No products found.') }}</flux:text>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($products as $product)
                    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
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
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts::app>
