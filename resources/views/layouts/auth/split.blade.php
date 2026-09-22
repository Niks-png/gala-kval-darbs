<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="relative hidden h-full flex-col overflow-hidden p-10 text-white lg:flex">
                <div class="absolute inset-0 bg-linear-to-br from-emerald-900 via-emerald-950 to-neutral-950"></div>
                <div class="absolute inset-0 opacity-20 [background-image:radial-gradient(circle_at_20%_20%,white,transparent_35%)]"></div>

                <a href="{{ route('home') }}" class="relative z-20 flex items-center text-lg font-medium" wire:navigate>
                    <span class="flex h-10 w-10 items-center justify-center rounded-md">
                        <x-app-logo-icon class="me-2 h-7 fill-current text-white" />
                    </span>
                    {{ __('Recepšu un cenu ceļvedis') }}
                </a>

                <div class="relative z-20 mt-auto">
                    <flux:heading size="xl" class="text-white">
                        {{ __('Atrodi ko pagatavot no tā, kas jau ir tavā virtuvē.') }}
                    </flux:heading>

                    <ul class="mt-8 space-y-5">
                        <li class="flex items-start gap-3">
                            <flux:icon.book-open-text class="mt-0.5 size-5 shrink-0 text-emerald-300" />
                            <span class="text-sm text-emerald-50">{{ __('Ievadi produktus, kas tev ir mājās, un atrodi piemērotas receptes.') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <flux:icon.chart-bar class="mt-0.5 size-5 shrink-0 text-emerald-300" />
                            <span class="text-sm text-emerald-50">{{ __('Seko produktu cenu izmaiņām un atrodi izdevīgāko piedāvājumu.') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <flux:icon.map class="mt-0.5 size-5 shrink-0 text-emerald-300" />
                            <span class="text-sm text-emerald-50">{{ __('Apskati tuvākos Maxima un top! veikalus kartē visā Latvijā.') }}</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <a href="{{ route('home') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
                        <span class="flex h-9 w-9 items-center justify-center rounded-md">
                            <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                        </span>

                        <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                    </a>
                    {{ $slot }}
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
