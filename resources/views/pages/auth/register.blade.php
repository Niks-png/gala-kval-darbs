<x-layouts::auth :title="__('Reģistrēties')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Izveido kontu')" :description="__('Ievadi savus datus, lai izveidotu kontu')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Vārds')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Vārds un uzvārds')"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('E-pasta adrese')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Parole')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Parole')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <x-password-requirements />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Apstiprināt paroli')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Apstiprināt paroli')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Izveidot kontu') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Jau ir konts?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Pieslēgties') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
