@if ($paginator->hasPages())
    @php
        $linkClass = 'inline-flex min-w-9 items-center justify-center border border-neutral-300 px-3 py-2 text-sm transition hover:bg-neutral-100 dark:border-neutral-600 dark:hover:bg-neutral-800';
        $disabledClass = 'inline-flex min-w-9 items-center justify-center border border-neutral-300 px-3 py-2 text-sm text-neutral-400 dark:border-neutral-700 dark:text-neutral-600';
    @endphp

    <nav role="navigation" aria-label="{{ __('Lapas') }}" class="flex flex-col items-center gap-3">
        <p class="text-sm text-neutral-500">
            {{ __('Rāda :first–:last no :total', ['first' => $paginator->firstItem(), 'last' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </p>

        <div class="flex -space-x-px overflow-hidden rounded-lg">
            @if ($paginator->onFirstPage())
                <span class="{{ $disabledClass }} rounded-s-lg" aria-disabled="true" aria-label="{{ __('Iepriekšējā') }}">‹</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $linkClass }} rounded-s-lg" aria-label="{{ __('Iepriekšējā') }}">‹</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="{{ $disabledClass }} hidden sm:inline-flex" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="inline-flex min-w-9 items-center justify-center border border-emerald-400 bg-emerald-400 px-3 py-2 text-sm font-medium text-ink">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="{{ $linkClass }} hidden sm:inline-flex" aria-label="{{ __('Lapa :page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $linkClass }} rounded-e-lg" aria-label="{{ __('Nākamā') }}">›</a>
            @else
                <span class="{{ $disabledClass }} rounded-e-lg" aria-disabled="true" aria-label="{{ __('Nākamā') }}">›</span>
            @endif
        </div>
    </nav>
@endif
