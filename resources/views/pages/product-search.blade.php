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

        <form method="GET" action="{{ route('products.search') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="filter-query" class="mb-1 block text-sm text-neutral-500">{{ __('Search products') }}</label>
                <input
                    id="filter-query"
                    type="search"
                    name="q"
                    value="{{ $query }}"
                    placeholder="{{ __('Search products') }}"
                    class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-600 dark:bg-neutral-800 dark:text-white"
                >
            </div>
            <div class="sm:w-48">
                <label for="filter-store" class="mb-1 block text-sm text-neutral-500">{{ __('Store') }}</label>
                <select
                    id="filter-store"
                    name="store"
                    class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-600 dark:bg-neutral-800 dark:text-white"
                >
                    <option value="">{{ __('All stores') }}</option>
                    @foreach ($stores as $storeOption)
                        <option value="{{ $storeOption }}" @selected($store === $storeOption)>{{ $storeOption }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:w-48">
                <label for="filter-category" class="mb-1 block text-sm text-neutral-500">{{ __('Category') }}</label>
                <select
                    id="filter-category"
                    name="category"
                    class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-600 dark:bg-neutral-800 dark:text-white"
                >
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $categoryOption)
                        <option value="{{ $categoryOption }}" @selected($category === $categoryOption)>{{ $categoryOption }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">
                    {{ __('Filter') }}
                </button>
                @if ($query !== '' || $store !== '' || $category !== '')
                    <a href="{{ route('products.search') }}" class="rounded-lg border border-neutral-300 px-5 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-300 dark:hover:bg-neutral-800">
                        {{ __('Reset') }}
                    </a>
                @endif
            </div>
        </form>

        @if ($query === '' && $store === '' && $category === '')
            <flux:text>{{ __('Enter a product name or choose a filter to search.') }}</flux:text>
        @elseif ($products->isEmpty())
            <flux:text>{{ __('No products found.') }}</flux:text>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($products as $product)
                    <form method="POST" action="{{ route('cart.items.store', $product) }}" class="add-to-cart-form">
                        @csrf
                        <button type="submit" class="block w-full overflow-hidden rounded-xl border border-neutral-200 text-start shadow-sm transition hover:border-emerald-500 hover:shadow-md dark:border-neutral-700">
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
                            @if ($product->latestPriceHistory)
                                <flux:text class="text-neutral-500 line-through">
                                    {{ number_format((float) $product->latestPriceHistory->previous_price, 2) }} €
                                </flux:text>
                            @endif
                            @if ($product->unit_price !== null && $product->unit !== null)
                                <flux:text>{{ number_format((float) $product->unit_price, 2) }} {{ $product->unit }}</flux:text>
                            @endif
                        </div>
                        <flux:text class="mt-3 text-emerald-600 dark:text-emerald-400">{{ __('Add to cart') }}</flux:text>
                        </div>
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
