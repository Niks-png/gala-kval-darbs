<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700">
                <flux:text>{{ __('Produkti') }}</flux:text>
                <flux:heading size="xl">{{ $productCount }}</flux:heading>
            </div>
            <div class="rounded-xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700">
                <flux:text>{{ __('Veikali') }}</flux:text>
                <flux:heading size="xl">{{ $storeCount }}</flux:heading>
            </div>
            <div class="rounded-xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700">
                <flux:text>{{ __('Cenu kritumi (7 dienas)') }}</flux:text>
                <flux:heading size="xl" class="text-emerald-600 dark:text-emerald-400">{{ $recentPriceDrops }}</flux:heading>
            </div>
        </div>

        <div>
            <flux:heading size="xl">{{ __('Produkti') }}</flux:heading>

            @if ($products->isEmpty())
                @if ($query !== '')
                    <flux:text class="mt-2">{{ __('Nekas netika atrasts ar šo meklējumu.') }}</flux:text>
                @else
                    <flux:text class="mt-2">{{ __('Vēl nav neviena produkta. Importē tos, lai tie parādītos šeit.') }}</flux:text>
                @endif
            @else
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
                                        <flux:text class="text-zinc-500 line-through">
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
