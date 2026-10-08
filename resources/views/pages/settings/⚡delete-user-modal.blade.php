<?php

use App\Concerns\PasswordValidationRules;
use App\Exceptions\LastAdminException;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        // Checked before logging out, so the last admin stays signed in and sees why. (Logging out
        // must come first: it saves the remember-me token, which would re-insert a deleted user.)
        if (Auth::user()->isLastAdmin()) {
            $this->addError('password', (new LastAdminException)->getMessage());

            return;
        }

        // Shared lists the user owns pass to another member before the account goes (User::booted),
        // which also re-checks the last admin under a lock in case another admin changed meanwhile.
        $user = tap(Auth::user(), $logout(...));
        DB::transaction(fn () => $user->delete());

        $this->redirect('/', navigate: true);
    }
}; ?>

<flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form method="POST" wire:submit="deleteUser" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Vai tiešām vēlies dzēst savu kontu?') }}</flux:heading>

            <flux:subheading>
                {{ __('Kad konts tiks dzēsts, visi tā dati tiks neatgriezeniski izdzēsti. Ievadi paroli, lai apstiprinātu konta dzēšanu.') }}
            </flux:subheading>
        </div>

        <flux:input wire:model="password" :label="__('Parole')" type="password" viewable />

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Atcelt') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit" data-test="confirm-delete-user-button">
                {{ __('Dzēst kontu') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
