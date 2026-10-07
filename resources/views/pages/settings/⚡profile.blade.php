<?php

use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profila iestatījumi')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        // A changed address has to be confirmed again before the app can be used.
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        Flux::toast(variant: 'success', text: __('Profils atjaunināts.'));
    }

}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profila iestatījumi') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profils')" :subheading="__('Maini savu vārdu un e-pasta adresi')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Vārds')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('E-pasts')" type="email" required autocomplete="email" />

            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Saglabāt') }}
                    </flux:button>
                </div>

            </div>
        </form>

            <livewire:pages::settings.delete-user-form />
    </x-pages::settings.layout>
</section>
