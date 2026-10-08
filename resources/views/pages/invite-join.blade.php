<x-layouts::app :title="__('Uzaicinājums')">
    <div class="flex h-full w-full flex-1 flex-col items-center justify-center gap-6">
        <div class="w-full max-w-md rounded-xl border border-neutral-200 p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
            @if ($list === null)
                <flux:heading size="lg">{{ __('Uzaicinājuma saite nedarbojas') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Saite ir beigusies vai saraksta īpašnieks to ir atslēdzis. Palūdz īpašniekam jaunu saiti.') }}</flux:text>
                <flux:button :href="route('cart')" wire:navigate class="mt-4">{{ __('Uz iepirkumu sarakstiem') }}</flux:button>
            @else
                <flux:heading size="lg">{{ __('Pievienoties sarakstam ":list"?', ['list' => $list->name]) }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __(':owner tevi aicina pievienoties kā :role.', [
                        'owner' => $list->user->name,
                        'role' => $list->invite_role === \App\Models\ShoppingList::ROLE_EDITOR ? __('rediģētājam (vari mainīt preces)') : __('skatītājam (vari tikai skatīties)'),
                    ]) }}
                </flux:text>

                <div class="mt-5 flex items-center gap-3">
                    <form method="POST" action="{{ route('cart.invite.join', $token) }}">
                        @csrf
                        <flux:button type="submit" variant="primary" data-test="join-list-button">{{ __('Pievienoties') }}</flux:button>
                    </form>
                    <flux:button :href="route('cart')" wire:navigate variant="ghost">{{ __('Nē, paldies') }}</flux:button>
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
