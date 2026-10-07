<x-layouts::auth :title="__('Pieslēgties')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Pieslēdzies savam kontam')" :description="__('Ievadi e-pastu un paroli, lai pieslēgtos')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />


        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('E-pasta adrese')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Parole')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Parole')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Aizmirsi paroli?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Atcerēties mani')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Pieslēgties') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Vēl nav konta?') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('Reģistrēties') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
