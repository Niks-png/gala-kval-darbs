<x-layouts::app :title="__('Administrācija')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('Administrācija') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Veikalu cenu atjaunošana, statistika un lietotāji.') }}</flux:text>
            </div>
            <div class="flex items-center gap-2">
                <flux:button :href="route('admin.users')" icon="users" wire:navigate>{{ __('Lietotāji') }}</flux:button>
                <form method="POST" action="{{ route('admin.scrape') }}">
                    @csrf
                    <flux:button type="submit" variant="primary" icon="arrow-path">{{ __('Atjaunot visas cenas') }}</flux:button>
                </form>
            </div>
        </div>

        @if (session('error'))
            <flux:text class="text-amber-600 dark:text-amber-400" data-test="admin-error">{{ session('error') }}</flux:text>
        @endif
        @if (session('success'))
            <flux:text class="text-emerald-600 dark:text-emerald-400">{{ session('success') }}</flux:text>
        @endif

        @if ($pendingScrapes > 0)
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                {{ trans_choice('{1} :count cenu atjaunošana gaida rindā.|[2,*] :count cenu atjaunošanas gaida rindā.', $pendingScrapes) }}
                {{ __('Ja tā nesākas, palaid rindas apstrādi:') }} <code class="font-mono">php artisan queue:work</code>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['label' => __('Lietotāji'), 'value' => $totals['users'], 'icon' => 'users'],
                ['label' => __('Produkti'), 'value' => $totals['products'], 'icon' => 'shopping-bag'],
                ['label' => __('Iepirkumu saraksti'), 'value' => $totals['lists'], 'icon' => 'shopping-cart'],
                ['label' => __('Cenu izmaiņas 24 h'), 'value' => $totals['priceChanges'], 'icon' => 'chart-bar'],
            ] as $stat)
                <div class="rounded-xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <div class="flex items-center justify-between">
                        <flux:text>{{ $stat['label'] }}</flux:text>
                        <flux:icon :name="$stat['icon']" class="size-5 text-neutral-400" />
                    </div>
                    <flux:heading size="xl" class="mt-2 font-mono tabular-nums">{{ lv_number($stat['value'], 0) }}</flux:heading>
                </div>
            @endforeach
        </div>

        <section class="space-y-3">
            <flux:heading size="lg">{{ __('Veikali') }}</flux:heading>
            <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                        <tr>
                            <th class="px-4 py-3">{{ __('Veikals') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Produkti') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Ar atlaidi') }}</th>
                            <th class="px-4 py-3">{{ __('Cenas atjaunotas') }}</th>
                            <th class="px-4 py-3">{{ __('Pēdējā atjaunošana') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach ($stores as $store)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $store['domain'] }}</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums">{{ lv_number($store['products'], 0) }}</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums">{{ lv_number($store['discounted'], 0) }}</td>
                                <td class="px-4 py-3 text-neutral-500">{{ $store['lastUpdated']?->diffForHumans() ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @include('pages.admin.partials.run-status', ['run' => $store['lastRun']])
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.scrape') }}">
                                        @csrf
                                        <input type="hidden" name="store" value="{{ $store['key'] }}">
                                        <flux:button type="submit" size="sm" icon="arrow-path">{{ __('Atjaunot') }}</flux:button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <flux:text class="text-xs">{{ __('Cenas automātiski atjaunojas katru dienu plkst. :time.', ['time' => config('services.scraper.daily_at')]) }}</flux:text>
        </section>

        <section class="space-y-3">
            <flux:heading size="lg">{{ __('Atjaunošanas vēsture') }}</flux:heading>
            @if ($recentRuns->isEmpty())
                <flux:text>{{ __('Cenas vēl nav atjaunotas.') }}</flux:text>
            @else
                <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700">
                    <table class="w-full min-w-[560px] text-left text-sm">
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-neutral-900">
                            <tr>
                                <th class="px-4 py-3">{{ __('Sākta') }}</th>
                                <th class="px-4 py-3">{{ __('Veikals') }}</th>
                                <th class="px-4 py-3">{{ __('Statuss') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Ilgums') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($recentRuns as $run)
                                <tr class="align-top">
                                    <td class="px-4 py-3 whitespace-nowrap font-mono tabular-nums">{{ $run->started_at->local()->format('d.m.Y H:i') }}</td>
                                    <td class="px-4 py-3">{{ $run->store }}</td>
                                    <td class="px-4 py-3">
                                        @include('pages.admin.partials.run-status', ['run' => $run])
                                        @if ($run->error)
                                            <pre class="mt-2 max-h-32 overflow-auto whitespace-pre-wrap rounded bg-neutral-100 p-2 text-xs text-red-700 dark:bg-neutral-900 dark:text-red-400">{{ $run->error }}</pre>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono tabular-nums">{{ $run->durationInSeconds() !== null ? $run->durationInSeconds().' s' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="space-y-3">
            <flux:heading size="lg">{{ __('Žurnāls') }}</flux:heading>
            @if ($log)
                <pre class="max-h-96 overflow-auto rounded-xl border border-neutral-200 bg-neutral-50 p-4 font-mono text-xs leading-relaxed dark:border-neutral-700 dark:bg-neutral-900">{{ $log }}</pre>
            @else
                <flux:text>{{ __('Žurnāls ir tukšs (storage/logs/scrape.log).') }}</flux:text>
            @endif
        </section>
    </div>
</x-layouts::app>
