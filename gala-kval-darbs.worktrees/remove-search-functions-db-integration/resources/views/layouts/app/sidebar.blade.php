<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="desktop" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate class="in-data-flux-sidebar-collapsed-desktop:hidden" />
                <flux:sidebar.collapse class="in-data-flux-sidebar-collapsed-desktop:opacity-100 in-data-flux-sidebar-collapsed-desktop:static" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Sākums') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="book-open-text" :href="route('recipes')" :current="request()->routeIs('recipes')" wire:navigate>
                    Receptes
                </flux:sidebar.item>
                <flux:sidebar.item icon="map" :href="route('map')" :current="request()->routeIs('map')" wire:navigate>
                    {{ __('Karte') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="shopping-cart" :href="route('cart')" :current="request()->routeIs('cart')" wire:navigate>
                    {{ __('Iepirkumu saraksts') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="chart-bar" :href="route('price-history')" :current="request()->routeIs('price-history')" wire:navigate>
                    {{ __('Cenu vēsture') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <flux:header container class="sticky top-0 z-20 border-b border-zinc-200 bg-zinc-50/95 shadow-sm backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
            <flux:spacer />

            <form method="GET" action="{{ route('products.search') }}" class="block w-full max-w-md">
                <label for="header-search" class="sr-only">{{ __('Search products') }}</label>
                <div class="relative">
                    <flux:icon.magnifying-glass class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                    <input
                        id="header-search"
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="{{ __('Search products') }}"
                        class="w-full rounded-full border border-zinc-300 bg-white py-2 ps-9 pe-4 text-sm text-zinc-900 shadow-sm outline-none transition placeholder:text-zinc-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white"
                    >
                </div>
            </form>

            <flux:spacer />

            <flux:navbar>
                <flux:navbar.item
                    icon="shopping-cart"
                    :href="route('cart')"
                    :current="request()->routeIs('cart')"
                    :label="__('Cart')"
                    wire:navigate
                />
            </flux:navbar>
        </flux:header>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
