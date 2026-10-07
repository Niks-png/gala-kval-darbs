<x-layouts::auth :title="__('Atjaunot paroli')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Atjaunot paroli')" :description="__('Ievadi savu jauno paroli')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Token -->
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <!-- Email Address -->
            <flux:input
                name="email"
                value="{{ request('email') }}"
                :label="__('E-pasts')"
                type="email"
                required
                autocomplete="email"
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
                <flux:button type="submit" variant="primary" class="w-full" data-test="reset-password-button">
                    {{ __('Atjaunot paroli') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::auth>
