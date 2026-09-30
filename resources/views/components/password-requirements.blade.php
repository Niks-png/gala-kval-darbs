{{--
    Live checklist for a new password. Must sit inside the same <form> as the
    autocomplete="new-password" inputs (first = password, second = confirmation).
    The rules come from Password::defaults(), so they always match validation.
    Checking happens in resources/js/app.js.
--}}
@php
    $rules = \Illuminate\Validation\Rules\Password::default()->appliedRules();

    $items = ['min' => __('Vismaz :min rakstzīmes', ['min' => $rules['min']])];

    if ($rules['mixedCase']) {
        $items['lower'] = __('Mazais burts (a–z)');
        $items['upper'] = __('Lielais burts (A–Z)');
    } elseif ($rules['letters']) {
        $items['letters'] = __('Vismaz viens burts');
    }

    if ($rules['numbers']) {
        $items['numbers'] = __('Vismaz viens cipars');
    }

    if ($rules['symbols']) {
        $items['symbols'] = __('Vismaz viens simbols (!, ?, # …)');
    }

    $items['match'] = __('Paroles sakrīt');
@endphp

<ul data-password-requirements data-min="{{ $rules['min'] }}" wire:ignore {{ $attributes->class('-mt-2 grid gap-1.5 text-sm') }}>
    @foreach ($items as $rule => $label)
        <li data-rule="{{ $rule }}" class="group flex items-center gap-2 text-zinc-400 transition-colors data-met:text-green-400">
            <span aria-hidden="true" class="flex size-4 shrink-0 items-center justify-center rounded-full border border-zinc-600 text-[10px] leading-none transition-colors group-data-met:border-green-400 group-data-met:bg-green-400 group-data-met:text-ink">
                <span class="hidden group-data-met:inline">✓</span>
            </span>
            <span>{{ $label }}</span>
            <span class="sr-only group-data-met:hidden">{{ __('(nav izpildīts)') }}</span>
            <span class="sr-only hidden group-data-met:inline">{{ __('(izpildīts)') }}</span>
        </li>
    @endforeach

    @if ($rules['uncompromised'])
        <li class="flex items-start gap-2 text-xs text-zinc-500">
            <span aria-hidden="true" class="flex size-4 shrink-0 items-center justify-center">ⓘ</span>
            <span>{{ __('Parole nedrīkst būt atrasta zināmās datu noplūdēs (pārbauda, saglabājot).') }}</span>
        </li>
    @endif
</ul>
