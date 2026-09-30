<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <flux:text>{{ __('Produkti') }}</flux:text>
                <flux:heading size="xl">{{ $productCount }}</flux:heading>
            </div>
            <div class="rounded-xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <flux:text>{{ __('Veikali') }}</flux:text>
                <flux:heading size="xl">{{ $storeCount }}</flux:heading>
            </div>
            <div class="rounded-xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 dark:bg-[radial-gradient(140%_120%_at_100%_0%,rgba(255,122,61,.35),transparent_60%)]">
                <flux:text>{{ __('Cenu kritumi (7 dienas)') }}</flux:text>
                <flux:heading size="xl" class="text-emerald-600 dark:text-emerald-400">{{ $recentPriceDrops }}</flux:heading>
            </div>
        </div>

        <div>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <flux:heading size="xl">{{ __('Produkti') }}</flux:heading>

                <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-3">
                    @if ($query !== '')
                        <input type="hidden" name="q" value="{{ $query }}">
                    @endif
                    <div>
                        <label for="dashboard-store" class="mb-1 block text-sm text-neutral-500">{{ __('Veikals') }}</label>
                        <select id="dashboard-store" name="store" onchange="this.form.submit()"
                            class="w-44 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-600 dark:bg-neutral-800 dark:text-white">
                            <option value="">{{ __('Visi veikali') }}</option>
                            @foreach ($stores as $storeOption)
                                <option value="{{ $storeOption }}" @selected($store === $storeOption)>{{ $storeOption }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="dashboard-category" class="mb-1 block text-sm text-neutral-500">{{ __('Kategorija') }}</label>
                        <select id="dashboard-category" name="category" onchange="this.form.submit()" @disabled($categories->isEmpty())
                            class="w-56 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 disabled:opacity-50 dark:border-neutral-600 dark:bg-neutral-800 dark:text-white"
                            @if ($categories->isEmpty()) title="{{ __('Palaid php artisan products:categorize') }}" @endif>
                            <option value="">{{ __('Visas kategorijas') }}</option>
                            @foreach ($categories as $categoryOption)
                                <option value="{{ $categoryOption }}" @selected($category === $categoryOption)>{{ $categoryOption }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($query !== '' || $store !== '' || $category !== '')
                        <a href="{{ route('dashboard') }}" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-300 dark:hover:bg-neutral-800">
                            {{ __('Notīrīt') }}
                        </a>
                    @endif
                </form>
            </div>

            @if ($query !== '' || $store !== '' || $category !== '')
                <flux:text class="mt-2">{{ trans_choice('{0} Nav atrasts neviens produkts|{1} Atrasts :count produkts|[2,*] Atrasti :count produkti', $products->total()) }}</flux:text>
            @endif

            @if ($products->isEmpty())
                @if ($query !== '' || $store !== '' || $category !== '')
                    <flux:text class="mt-2">{{ __('Nekas netika atrasts ar šo meklējumu.') }}</flux:text>
                @else
                    <flux:text class="mt-2">{{ __('Vēl nav neviena produkta. Importē tos, lai tie parādītos šeit.') }}</flux:text>
                @endif
            @else
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    </div>

    <x-add-to-cart-toast />
</x-layouts::app>
