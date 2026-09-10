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
                    <form method="POST" action="{{ route('cart.items.store', $product) }}" class="add-to-cart-form">
                        @csrf
                        <button type="submit" class="block w-full rounded-xl border border-neutral-200 p-4 text-start transition hover:border-emerald-500 hover:shadow-md dark:border-neutral-700">
                        <flux:heading size="sm">{{ $product->title }}</flux:heading>
                        <flux:text class="mt-1">{{ $product->store }}</flux:text>
                        <div class="mt-4 flex items-baseline justify-between gap-3">
                            <flux:heading size="lg">
                                {{ $product->current_price !== null ? number_format((float) $product->current_price, 2) . ' €' : '—' }}
                            </flux:heading>
                            @if ($product->latestPriceHistory)
                                <flux:text class="text-zinc-500 line-through">
                                    {{ number_format((float) $product->latestPriceHistory->previous_price, 2) }} €
                                </flux:text>
                            @endif
                            @if ($product->unit_price !== null && $product->unit !== null)
                                <flux:text>{{ number_format((float) $product->unit_price, 2) }} {{ $product->unit }}</flux:text>
                            @endif
                        </div>
                        <flux:text class="mt-3 text-emerald-600 dark:text-emerald-400">{{ __('Add to cart') }}</flux:text>
                        </button>
                    </form>
                @endforeach
            </div>
        @endif
    </div>

    <div id="cart-success-toast" class="pointer-events-none fixed bottom-6 end-6 z-50 hidden max-w-sm rounded-lg bg-emerald-600 px-4 py-3 text-sm font-medium text-white shadow-lg" role="status">
        Produkts veiksmīgi pievienots iepirkuma sarakstam
    </div>

    <script>
        document.querySelectorAll('.add-to-cart-form').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                    },
                });

                if (!response.ok) {
                    return;
                }

                const toast = document.getElementById('cart-success-toast');
                toast.classList.remove('hidden');

                window.setTimeout(() => {
                    toast.classList.add('hidden');
                }, 3000);
            });
        });
    </script>
</x-layouts::app>
