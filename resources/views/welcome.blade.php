<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-neutral-950">
        <div class="relative overflow-hidden">
            <div class="absolute inset-0 bg-linear-to-br from-emerald-900 via-emerald-950 to-neutral-950"></div>
            <div class="absolute inset-0 opacity-20 [background-image:radial-gradient(circle_at_15%_15%,white,transparent_35%)]"></div>

            <header class="relative z-10 mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-medium text-white" wire:navigate>
                    <x-app-logo-icon class="size-8 fill-current text-white" />
                    <span class="text-sm text-emerald-100">{{ __('Recepšu un cenu ceļvedis') }}</span>
                </a>

                @if (Route::has('login'))
                    <nav class="flex items-center gap-3">
                        @auth
                            <flux:button :href="route('dashboard')" variant="primary" wire:navigate>
                                {{ __('Dashboard') }}
                            </flux:button>
                        @else
                            <flux:button :href="route('login')" variant="ghost" class="!text-white hover:!bg-white/10" wire:navigate>
                                {{ __('Log in') }}
                            </flux:button>

                            @if (Route::has('register'))
                                <flux:button :href="route('register')" variant="primary" wire:navigate>
                                    {{ __('Sign up') }}
                                </flux:button>
                            @endif
                        @endauth
                    </nav>
                @endif
            </header>

            <div class="relative z-10 mx-auto max-w-3xl px-6 pb-24 pt-12 text-center sm:pb-32 sm:pt-16">
                <flux:heading size="xl" level="1" class="text-4xl text-white sm:text-6xl">
                    {{ __('Atrodi ko pagatavot no tā, kas jau ir tavā virtuvē.') }}
                </flux:heading>
                <p class="mx-auto mt-5 max-w-xl text-lg text-emerald-50">
                    {{ __('Ievadi produktus, kas tev jau ir mājās, seko līdzi cenu izmaiņām un atrodi tuvāko Maxima vai top! veikalu — viss vienuviet.') }}
                </p>

                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    @auth
                        <flux:button :href="route('dashboard')" variant="primary" wire:navigate>
                            {{ __('Uz sākumu') }}
                        </flux:button>
                    @else
                        <flux:button :href="route('register')" variant="primary" wire:navigate>
                            {{ __('Reģistrēties') }}
                        </flux:button>
                        <flux:button :href="route('login')" variant="ghost" class="!text-white hover:!bg-white/10" wire:navigate>
                            {{ __('Man jau ir konts') }}
                        </flux:button>
                    @endauth
                </div>
            </div>
        </div>

        <main class="mx-auto w-full max-w-5xl px-6 py-20 sm:py-28">
            <flux:heading size="lg" class="text-center">{{ __('Kā tas strādā') }}</flux:heading>

            <div class="mt-12 grid gap-10 sm:grid-cols-3">
                <div class="text-center sm:text-start">
                    <span class="text-5xl font-semibold text-emerald-600/30 dark:text-emerald-400/30">01</span>
                    <flux:heading size="sm" class="mt-2 flex items-center justify-center gap-2 sm:justify-start">
                        <flux:icon.book-open-text class="size-5 text-emerald-600 dark:text-emerald-400" />
                        {{ __('Ievadi produktus') }}
                    </flux:heading>
                    <flux:text class="mt-2">
                        {{ __('Uzraksti, kas tev jau ir mājās, un uzreiz saņem piemērotu recepšu izlasi.') }}
                    </flux:text>
                </div>

                <div class="text-center sm:text-start">
                    <span class="text-5xl font-semibold text-emerald-600/30 dark:text-emerald-400/30">02</span>
                    <flux:heading size="sm" class="mt-2 flex items-center justify-center gap-2 sm:justify-start">
                        <flux:icon.chart-bar class="size-5 text-emerald-600 dark:text-emerald-400" />
                        {{ __('Seko cenām') }}
                    </flux:heading>
                    <flux:text class="mt-2">
                        {{ __('Redzi produktu cenu vēsturi laika gaitā un pamani, kad cena krīt.') }}
                    </flux:text>
                </div>

                <div class="text-center sm:text-start">
                    <span class="text-5xl font-semibold text-emerald-600/30 dark:text-emerald-400/30">03</span>
                    <flux:heading size="sm" class="mt-2 flex items-center justify-center gap-2 sm:justify-start">
                        <flux:icon.map class="size-5 text-emerald-600 dark:text-emerald-400" />
                        {{ __('Atrodi veikalu') }}
                    </flux:heading>
                    <flux:text class="mt-2">
                        {{ __('Apskati tuvāko Maxima vai top! veikalu kartē jebkur Latvijā.') }}
                    </flux:text>
                </div>
            </div>
        </main>

        <footer class="border-t border-neutral-200 py-8 dark:border-neutral-800">
            <flux:text class="text-center">
                &copy; {{ date('Y') }} {{ __('Recepšu un cenu ceļvedis') }}
            </flux:text>
        </footer>

        @fluxScripts
    </body>
</html>
