<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="auth-citrus min-h-screen antialiased">
        <div class="flex min-h-dvh items-center justify-center p-4 sm:p-8">
            <div class="grid w-full max-w-5xl overflow-hidden rounded-3xl border border-zinc-700 bg-zinc-900 shadow-2xl shadow-black/50 lg:grid-cols-2">
                {{-- Left: brand panel --}}
                <div class="relative hidden min-h-[560px] flex-col overflow-hidden p-10 text-white lg:flex">
                    <div class="absolute inset-0 bg-[radial-gradient(120%_90%_at_100%_0%,#ff7a3d_0%,#c2410c_28%,#3b1a4a_62%,#17121f_100%)]"></div>
                    <div class="absolute inset-0 bg-linear-to-t from-zinc-950/70 via-transparent to-transparent"></div>

                    {{-- Citrus slice --}}
                    <svg class="citrus-slice pointer-events-none absolute right-6 top-16 size-48 drop-shadow-[0_20px_40px_rgba(0,0,0,0.35)]" viewBox="0 0 200 200" aria-hidden="true">
                        <circle cx="100" cy="100" r="96" fill="#f6dfb8" />
                        <circle cx="100" cy="100" r="84" fill="#f7c35f" />
                        <g stroke="#f3a93a" stroke-width="5" stroke-linecap="round">
                            @for ($i = 0; $i < 10; $i++)
                                <line x1="100" y1="100" x2="{{ 100 + 80 * cos(deg2rad($i * 36)) }}" y2="{{ 100 + 80 * sin(deg2rad($i * 36)) }}" />
                            @endfor
                        </g>
                        <circle cx="100" cy="100" r="6" fill="#f3a93a" />
                    </svg>

                    <a href="{{ route('home') }}" class="relative z-20 flex items-center gap-3 font-semibold" wire:navigate>
                        <span class="flex size-8 items-center justify-center rounded-lg bg-emerald-300 text-sm font-extrabold text-ink">R</span>
                        {{ __('Recepšu un cenu ceļvedis') }}
                    </a>

                    <div class="relative z-20 mt-auto">
                        <h2 class="text-3xl font-extrabold leading-tight tracking-tight">
                            {{ __('Atrodi ko pagatavot no tā, kas jau ir tavā virtuvē.') }}
                        </h2>

                        <ul class="mt-6 space-y-4 text-sm text-zinc-200">
                            <li class="flex items-start gap-3">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-md bg-emerald-300/20 text-xs text-emerald-300">✦</span>
                                <span>{{ __('Ievadi produktus, kas tev ir mājās, un atrodi receptes.') }}</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-md bg-emerald-300/20 text-xs text-emerald-300">↓</span>
                                <span>{{ __('Seko cenu izmaiņām un atrodi izdevīgāko piedāvājumu.') }}</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-md bg-emerald-300/20 text-xs text-emerald-300">⧫</span>
                                <span>{{ __('Apskati tuvākos Maxima, top! un Rimi veikalus kartē.') }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                {{-- Right: form panel --}}
                <div class="flex items-center justify-center bg-zinc-900 px-6 py-10 sm:px-12">
                    <div class="flex w-full max-w-sm flex-col space-y-6">
                        <a href="{{ route('home') }}" class="flex items-center justify-center gap-3 font-semibold text-white lg:hidden" wire:navigate>
                            <span class="flex size-8 items-center justify-center rounded-lg bg-emerald-300 text-sm font-extrabold text-ink">R</span>
                            {{ __('Recepšu un cenu ceļvedis') }}
                        </a>
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
