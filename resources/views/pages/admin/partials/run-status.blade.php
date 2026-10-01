@if ($run === null)
    <span class="text-neutral-500">—</span>
@else
    @php($stale = $run->status === \App\Models\ScrapeRun::STATUS_RUNNING && $run->started_at->lt(now()->subHour()))
    <span @class([
        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $run->status === \App\Models\ScrapeRun::STATUS_SUCCESS,
        'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $run->status === \App\Models\ScrapeRun::STATUS_FAILED || $stale,
        'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $run->status === \App\Models\ScrapeRun::STATUS_RUNNING && ! $stale,
    ])>
        @if ($stale)
            {{ __('Pārtraukta') }}
        @elseif ($run->status === \App\Models\ScrapeRun::STATUS_SUCCESS)
            {{ __('Izdevās') }}
        @elseif ($run->status === \App\Models\ScrapeRun::STATUS_FAILED)
            {{ __('Neizdevās') }}
        @else
            {{ __('Notiek…') }}
        @endif
    </span>
    <span class="ms-1 text-xs text-neutral-500">{{ $run->started_at->diffForHumans() }}</span>
@endif
