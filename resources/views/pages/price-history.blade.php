<x-layouts::app :title="__('Price history')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div>
            <flux:heading size="xl">{{ __('Price history') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Each product\'s price trend over time. Newest changes first.') }}</flux:text>
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div class="min-w-48 flex-1">
                <label for="q" class="mb-1 block text-sm font-medium">{{ __('Search') }}</label>
                <input type="text" name="q" id="q" value="{{ $query }}" placeholder="{{ __('Product name') }}"
                    class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900" />
            </div>
            <div class="min-w-40">
                <label for="store" class="mb-1 block text-sm font-medium">{{ __('Store') }}</label>
                <select name="store" id="store" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                    <option value="">{{ __('All stores') }}</option>
                    @foreach ($stores as $storeOption)
                        <option value="{{ $storeOption }}" @selected($store === $storeOption)>{{ $storeOption }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">
                {{ __('Filter') }}
            </button>
        </form>

        @if ($products->isEmpty())
            <flux:text>{{ __('No price changes have been recorded yet.') }}</flux:text>
        @else
            <div class="space-y-3">
                @foreach ($products as $product)
                    @php
                        $history = $product->priceHistory;
                        $prices = $history->isNotEmpty()
                            ? [(float) $history->first()->previous_price, ...$history->pluck('new_price')->map(fn ($price) => (float) $price)]
                            : [];
                        $latest = $history->last();
                        $hasChanged = $latest && (float) $latest->previous_price !== (float) $latest->new_price;
                        $trend = ! $hasChanged ? 'flat' : ((float) $latest->new_price < (float) $latest->previous_price ? 'down' : 'up');
                        $changeAmount = $latest ? (float) $latest->new_price - (float) $latest->previous_price : 0.0;
                        $changePercent = $latest && (float) $latest->previous_price > 0
                            ? ($changeAmount / (float) $latest->previous_price) * 100
                            : 0.0;
                    @endphp
                    <details class="group rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                        <summary class="flex cursor-pointer list-none flex-wrap items-center gap-4">
                            <div class="min-w-48 flex-1">
                                <flux:heading size="sm">{{ $product->title }}</flux:heading>
                                <flux:text class="mt-1">{{ $product->store }}</flux:text>
                            </div>

                            <x-price-sparkline :points="$prices" :trend="$trend" />

                            <div class="min-w-32 text-end">
                                <flux:heading size="sm">
                                    {{ $product->current_price !== null ? number_format((float) $product->current_price, 2) . ' €' : '—' }}
                                </flux:heading>
                                @if ($hasChanged)
                                    <flux:text class="{{ $trend === 'down' ? 'text-emerald-600' : 'text-red-600' }}">
                                        {{ $changeAmount > 0 ? '+' : '' }}{{ number_format($changePercent, 1) }}%
                                        ({{ $changeAmount > 0 ? '+' : '' }}{{ number_format($changeAmount, 2) }} €)
                                    </flux:text>
                                @else
                                    <flux:text class="text-neutral-500">{{ __('No change yet') }}</flux:text>
                                @endif
                            </div>

                            <span class="text-neutral-400 transition group-open:rotate-180" aria-hidden="true">⌄</span>
                        </summary>

                        <div class="mt-4 overflow-x-auto border-t border-neutral-200 pt-4 dark:border-neutral-700">
                            <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                                <thead class="text-start">
                                    <tr>
                                        <th class="px-3 py-2 text-start font-medium">{{ __('Previous price') }}</th>
                                        <th class="px-3 py-2 text-start font-medium">{{ __('New price') }}</th>
                                        <th class="px-3 py-2 text-start font-medium">{{ __('Change') }}</th>
                                        <th class="px-3 py-2 text-start font-medium">{{ __('Changed') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                                    @foreach ($history->reverse() as $change)
                                        @php
                                            $rowChanged = (float) $change->previous_price !== (float) $change->new_price;
                                            $rowDown = $rowChanged && (float) $change->new_price < (float) $change->previous_price;
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-2">{{ number_format((float) $change->previous_price, 2) }} €</td>
                                            <td class="px-3 py-2 font-medium {{ $rowChanged ? ($rowDown ? 'text-emerald-600' : 'text-red-600') : '' }}">
                                                {{ number_format((float) $change->new_price, 2) }} €
                                            </td>
                                            <td class="px-3 py-2 {{ $rowChanged ? ($rowDown ? 'text-emerald-600' : 'text-red-600') : 'text-neutral-500' }}">
                                                {{ $rowChanged ? number_format((float) $change->new_price - (float) $change->previous_price, 2) . ' €' : __('No change') }}
                                            </td>
                                            <td class="px-3 py-2">{{ $change->created_at->format('d.m.Y H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts::app>
