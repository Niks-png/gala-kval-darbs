<x-layouts::app :title="__('Produktu meklēšana')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div>
            <flux:heading size="xl">{{ __('Produktu meklēšana') }}</flux:heading>
            @if ($query !== '')
                <flux:text class="mt-2">{{ __('Rezultāti meklējumam ":query"', ['query' => $query]) }}</flux:text>
            @endif
        </div>

        <form method="GET" action="{{ route('products.search') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="filter-query" class="mb-1 block text-sm text-neutral-500">{{ __('Meklēt produktus') }}</label>
                <input
                    id="filter-query"
                    type="search"
                    name="q"
                    value="{{ $query }}"
                    placeholder="{{ __('Produkta nosaukums') }}"
                    class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-600 dark:bg-neutral-800 dark:text-white"
                >
            </div>
            <div class="sm:w-48">
                <label for="filter-store" class="mb-1 block text-sm text-neutral-500">{{ __('Veikals') }}</label>
                <select
                    id="filter-store"
                    name="store"
                    class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-600 dark:bg-neutral-800 dark:text-white"
                >
                    <option value="">{{ __('Visi veikali') }}</option>
                    @foreach ($stores as $storeOption)
                        <option value="{{ $storeOption }}" @selected($store === $storeOption)>{{ $storeOption }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:w-56">
                <label for="filter-category" class="mb-1 block text-sm text-neutral-500">{{ __('Kategorija') }}</label>
                <select
                    id="filter-category"
                    name="category"
                    class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-600 dark:bg-neutral-800 dark:text-white"
                >
                    <option value="">{{ __('Visas kategorijas') }}</option>
                    @foreach ($categories as $categoryOption)
                        <option value="{{ $categoryOption }}" @selected($category === $categoryOption)>{{ $categoryOption }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">
                    {{ __('Meklēt') }}
                </button>
                @if ($products !== null)
                    <a href="{{ route('products.search') }}" class="rounded-lg border border-neutral-300 px-5 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-300 dark:hover:bg-neutral-800">
                        {{ __('Notīrīt') }}
                    </a>
                @endif
            </div>
        </form>

        @if ($products === null)
            <flux:text>{{ __('Ievadi produkta nosaukumu vai izvēlies filtru, lai meklētu.') }}</flux:text>
        @elseif ($products->isEmpty())
            <flux:text>{{ __('Neviens produkts netika atrasts.') }}</flux:text>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($products as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>

            {{ $products->links() }}
        @endif
    </div>

    <x-add-to-cart-toast />
</x-layouts::app>
