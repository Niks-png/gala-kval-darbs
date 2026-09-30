@props([
    'points' => [],
    'trend' => 'flat',
    'area' => false,
])

@php
    $width = 120;
    $height = 32;
    $pad = 3;
    $values = array_values($points);
    $count = count($values);
    $min = $count ? min($values) : 0;
    $max = $count ? max($values) : 0;
    $range = $max - $min;

    $xy = $count > 1
        ? collect($values)->map(function ($value, $index) use ($count, $width, $height, $pad, $min, $range) {
            $x = $index / ($count - 1) * $width;
            $y = $range > 0 ? $pad + ($height - 2 * $pad) * (1 - ($value - $min) / $range) : $height / 2;

            return [round($x, 2), round($y, 2)];
        })
        : collect();

    $coords = $xy->map(fn ($point) => implode(',', $point))->implode(' ');
    $last = $xy->last();

    $colorClass = match ($trend) {
        'down' => 'text-green-400',
        'up' => 'text-red-400',
        default => 'text-zinc-400',
    };
@endphp

<svg viewBox="0 0 {{ $width }} {{ $height }}" {{ $attributes->class([$colorClass, 'h-8 w-28 shrink-0' => ! $attributes->has('class')]) }} preserveAspectRatio="none" aria-hidden="true">
    @if ($count > 1)
        @if ($area)
            <polygon fill="currentColor" fill-opacity="0.12" points="0,{{ $height }} {{ $coords }} {{ $width }},{{ $height }}" />
        @endif
        <polyline fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" points="{{ $coords }}" />
        @if ($area)
            {{-- zero-length path + round cap = a dot that stays round when the SVG is stretched --}}
            <path d="M{{ $last[0] }} {{ $last[1] }}h0" stroke="currentColor" stroke-width="8" stroke-linecap="round" vector-effect="non-scaling-stroke" />
        @endif
    @else
        <line x1="0" y1="{{ $height / 2 }}" x2="{{ $width }}" y2="{{ $height / 2 }}" stroke="currentColor" stroke-opacity="0.5" stroke-width="2" stroke-dasharray="4 3" vector-effect="non-scaling-stroke" />
    @endif
</svg>
