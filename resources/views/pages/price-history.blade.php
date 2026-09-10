<x-layouts::app :title="__('Price history')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div>
            <flux:heading size="xl">{{ __('Price history') }}</flux:heading>
            <flux:text class="mt-2">{{ __('See how product prices have changed over time.') }}</flux:text>
        </div>

        @if ($history->isEmpty())
            <flux:text>{{ __('No price changes have been recorded yet.') }}</flux:text>
        @else
            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-50 text-start dark:bg-neutral-800">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Product') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Store') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Previous price') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('New price') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('Changed') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach ($history as $change)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $change->product?->title ?? __('Deleted product') }}</td>
                                <td class="px-4 py-3">{{ $change->product?->store ?? '—' }}</td>
                                <td class="px-4 py-3">{{ number_format((float) $change->previous_price, 2) }} €</td>
                                <td class="px-4 py-3 font-medium {{ (float) $change->new_price < (float) $change->previous_price ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ number_format((float) $change->new_price, 2) }} €
                                </td>
                                <td class="px-4 py-3">{{ $change->created_at->format('d.m.Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts::app>
