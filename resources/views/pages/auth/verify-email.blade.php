<x-layouts::auth :title="__('E-pasta apstiprināšana')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Apstiprini savu e-pastu')" :description="__('Mēs nosūtījām apstiprināšanas saiti uz :email. Atver e-pastu un noklikšķini uz saites, lai turpinātu.', ['email' => auth()->user()->email])" />

        @if (session('verification_mail_failed'))
            <div class="text-center text-sm font-medium text-amber-600" data-test="verification-mail-failed">
                {{ __('E-pastu neizdevās nosūtīt. Pēc brīža spied "Nosūtīt saiti vēlreiz".') }}
            </div>
        @elseif (session('status') === 'verification-link-sent')
            <div class="text-center text-sm font-medium text-green-600">
                {{ __('Jauna apstiprināšanas saite nosūtīta uz tavu e-pastu.') }}
            </div>
        @endif

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <flux:button variant="primary" type="submit" class="w-full" data-test="resend-verification-button">
                {{ __('Nosūtīt saiti vēlreiz') }}
            </flux:button>
        </form>

        <div class="flex items-center justify-center gap-4 text-sm">
            <flux:link :href="route('profile.edit')" wire:navigate>{{ __('Mainīt e-pasta adresi') }}</flux:link>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="cursor-pointer text-zinc-400 underline hover:text-zinc-600 dark:hover:text-zinc-200">
                    {{ __('Iziet') }}
                </button>
            </form>
        </div>
    </div>
</x-layouts::auth>
