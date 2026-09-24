<x-layouts::app :title="__('Paziņojumi')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div>
            <flux:heading size="xl">{{ __('Paziņojumi') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Uzaicinājumi pievienoties citu lietotāju iepirkumu sarakstiem.') }}</flux:text>
        </div>

        @if (session('success'))
            <flux:text class="text-emerald-600 dark:text-emerald-400">{{ session('success') }}</flux:text>
        @endif

        @if ($invitations->isEmpty())
            <div class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-neutral-300 p-10 text-center dark:border-neutral-700">
                <flux:icon.bell class="size-8 text-neutral-400" />
                <flux:text>{{ __('Tev nav jaunu paziņojumu.') }}</flux:text>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($invitations as $invitation)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-semibold text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300">
                                {{ $invitation->inviter->initials() }}
                            </span>
                            <div class="min-w-0">
                                <flux:heading size="sm">
                                    {{ __(':name tevi uzaicināja uz sarakstu ":list"', ['name' => $invitation->inviter->name, 'list' => $invitation->shoppingList->name]) }}
                                </flux:heading>
                                <flux:text>
                                    {{ $invitation->role === \App\Models\ShoppingList::ROLE_EDITOR ? __('Loma: Rediģētājs') : __('Loma: Skatītājs') }}
                                    · {{ $invitation->created_at->diffForHumans() }}
                                </flux:text>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('invitations.destroy', $invitation) }}">
                                @csrf
                                @method('DELETE')
                                <flux:button type="submit" size="sm">{{ __('Noraidīt') }}</flux:button>
                            </form>
                            <form method="POST" action="{{ route('invitations.accept', $invitation) }}">
                                @csrf
                                <flux:button type="submit" size="sm" variant="primary">{{ __('Pieņemt') }}</flux:button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts::app>
