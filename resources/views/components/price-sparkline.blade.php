@props([
    'points' => [],
    'trend' => 'flat',
])

@php
    $width = 120;
    $height = 32;
    $values = array_values($points);
    $count = count($values);
    $min = $count ? min($values) : 0;
    $max = $count ? max($values) : 0;
    $range = $max - $min;

    $coords = $count > 1
        ? collect($values)->map(function ($value, $index) use ($count, $width, $height, $min, $range) {
            $x = $index / ($count - 1) * $width;
            $y = $range > 0 ? $height - (($value - $min) / $range * $height) : $height / 2;

            return round($x, 2).','.round($y, 2);
        })->implode(' ')
        : '';

    $strokeColor = match ($trend) {
        'down' => '#059669',
        'up' => '#dc2626',
        default => '#a3a3a3',
    };
@endphp

<svg viewBox="0 0 {{ $width }} {{ $height }}" {{ $attributes->merge(['class' => 'h-8 w-28 shrink-0']) }} preserveAspectRatio="none" aria-hidden="true">
    @if ($count > 1)
        <polyline fill="none" stroke="{{ $strokeColor }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="{{ $coords }}" />
    @else
        <line x1="0" y1="{{ $height / 2 }}" x2="{{ $width }}" y2="{{ $height / 2 }}" stroke="{{ $strokeColor }}" stroke-width="2" stroke-dasharray="4 3" />
    @endif
</svg>
