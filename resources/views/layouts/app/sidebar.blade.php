@php($notificationCount = auth()->user()->shoppingListInvitations()->count() + auth()->user()->unreadNotifications()->count())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        {{-- Dark is the default until the user picks light with the theme toggle --}}
        <script>
            if (! window.localStorage.getItem('flux.appearance')) window.localStorage.setItem('flux.appearance', 'dark');
        </script>
        @fluxAppearance
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-950">
        <flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
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
                <flux:sidebar.item icon="bell" :href="route('notifications')" :current="request()->routeIs('notifications')" :badge="$notificationCount ?: null" wire:navigate>
                    {{ __('Paziņojumi') }}
                </flux:sidebar.item>
                @can('admin')
                    <flux:sidebar.item icon="shield-check" :href="route('admin.index')" :current="request()->routeIs('admin.*')" wire:navigate>
                        {{ __('Administrācija') }}
                    </flux:sidebar.item>
                @endcan
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <flux:header container class="hidden lg:block sticky top-0 z-20 border-b border-zinc-200 bg-zinc-50/95 shadow-sm backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
            <flux:spacer />

            <form method="GET" action="{{ request()->routeIs('dashboard') ? route('dashboard') : route('products.search') }}" class="block w-full max-w-md">
                <label for="header-search" class="sr-only">{{ __('Meklēt produktus') }}</label>
                <div class="relative">
                    <flux:icon.magnifying-glass class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                    <input
                        id="header-search"
                        type="search"
                        name="q"
                        value="{{ is_string(request('q')) ? request('q') : '' }}"
                        placeholder="{{ __('Meklēt produktus') }}"
                        class="w-full rounded-full border border-zinc-300 bg-white py-2 ps-9 pe-4 text-sm text-zinc-900 shadow-sm outline-none transition placeholder:text-zinc-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white"
                    >
                </div>
            </form>

            <flux:spacer />

            <flux:button x-data x-on:click="$flux.dark = ! $flux.dark" variant="subtle" square :aria-label="__('Pārslēgt gaišo / tumšo režīmu')" :tooltip="__('Gaišais / tumšais režīms')">
                <flux:icon.sun variant="mini" class="hidden dark:block" />
                <flux:icon.moon variant="mini" class="dark:hidden" />
            </flux:button>

            <flux:navbar>
                <flux:navbar.item
                    icon="bell"
                    :href="route('notifications')"
                    :current="request()->routeIs('notifications')"
                    :label="__('Paziņojumi')"
                    :badge="$notificationCount ?: null"
                    badge:color="red"
                    wire:navigate
                />
                <flux:navbar.item
                    icon="shopping-cart"
                    :href="route('cart')"
                    :current="request()->routeIs('cart')"
                    :label="__('Iepirkumu saraksts')"
                    wire:navigate
                />
            </flux:navbar>
        </flux:header>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:button x-data x-on:click="$flux.dark = ! $flux.dark" variant="subtle" square :aria-label="__('Pārslēgt gaišo / tumšo režīmu')" :tooltip="__('Gaišais / tumšais režīms')">
                <flux:icon.sun variant="mini" class="hidden dark:block" />
                <flux:icon.moon variant="mini" class="dark:hidden" />
            </flux:button>

            <flux:navbar>
                <flux:navbar.item
                    icon="bell"
                    :href="route('notifications')"
                    :label="__('Paziņojumi')"
                    :badge="$notificationCount ?: null"
                    badge:color="red"
                    wire:navigate
                />
            </flux:navbar>

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
                            {{ __('Iestatījumi') }}
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
                            {{ __('Iziet') }}
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
