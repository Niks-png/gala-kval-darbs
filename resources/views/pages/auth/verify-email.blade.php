<x-layouts::auth :title="__('Email verification')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Verify your email')" :description="__('We sent a verification link to :email. Click the link in the email to continue.', ['email' => auth()->user()->email])" />

        @if (session('status') === 'verification-link-sent')
            <div class="text-center text-sm font-medium text-green-600">
                {{ __('A new verification link has been sent to your email address.') }}
            </div>
        @endif

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <flux:button variant="primary" type="submit" class="w-full" data-test="resend-verification-button">
                {{ __('Resend verification email') }}
            </flux:button>
        </form>

        <div class="flex items-center justify-center gap-4 text-sm">
            <flux:link :href="route('profile.edit')" wire:navigate>{{ __('Change email address') }}</flux:link>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="cursor-pointer text-zinc-400 underline hover:text-zinc-600 dark:hover:text-zinc-200">
                    {{ __('Log out') }}
                </button>
            </form>
        </div>
    </div>
</x-layouts::auth>
