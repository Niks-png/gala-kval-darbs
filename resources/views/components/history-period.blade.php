@props(['current'])

{{-- Which period of price history a page shows; loading only that period keeps pages fast as history grows. --}}
<div {{ $attributes->class('flex flex-wrap items-center gap-1 text-sm') }} role="group" aria-label="{{ __('Cenu vēstures periods') }}">
    @foreach (\App\Http\Controllers\ProductController::HISTORY_PERIODS as $days => $label)
        <a href="{{ request()->fullUrlWithQuery(['period' => $days, 'page' => null]) }}" wire:navigate
            @if ($days === $current) aria-current="true" @endif
            @class([
                'rounded-full px-3 py-1 transition',
                'bg-emerald-600 text-white' => $days === $current,
                'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800' => $days !== $current,
            ])>{{ __($label) }}</a>
    @endforeach
</div>
