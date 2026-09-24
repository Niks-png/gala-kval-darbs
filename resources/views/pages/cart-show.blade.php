@use('App\Models\ShoppingList')

@php
    $canEdit = in_array($role, ['owner', ShoppingList::ROLE_EDITOR], true);
    $isOwner = $role === 'owner';
    $roleLabels = [
        'owner' => __('Īpašnieks'),
        ShoppingList::ROLE_EDITOR => __('Rediģētājs'),
        ShoppingList::ROLE_VIEWER => __('Skatītājs'),
    ];
    $people = collect([$list->user])->concat($list->members);
@endphp

<x-layouts::app :title="$list->name">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <a href="{{ route('cart') }}" wire:navigate class="text-sm text-emerald-600 hover:text-emerald-700">
                        {{ __('← Visi saraksti') }}
                    </a>
                    <flux:heading size="xl" class="mt-2 flex items-center gap-2">
                        {{ $list->name }}
                        <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">{{ $roleLabels[$role] }}</span>
                    </flux:heading>
                    @unless ($isOwner)
                        <flux:text class="mt-1">{{ __('Īpašnieks: :name', ['name' => $list->user->name]) }}</flux:text>
                    @endunless
                </div>

                <div class="flex items-center gap-2">
                    <flux:modal.trigger name="members">
                        <button type="button" class="flex items-center gap-2 rounded-lg px-2 py-1 transition hover:bg-neutral-100 dark:hover:bg-neutral-800" aria-label="{{ __('Dalībnieki') }}">
                            <span class="flex -space-x-2">
                                @foreach ($people->take(4) as $person)
                                    <span title="{{ $person->name }}" class="flex size-8 items-center justify-center rounded-full bg-emerald-100 text-xs font-semibold text-emerald-700 ring-2 ring-white dark:bg-emerald-900 dark:text-emerald-300 dark:ring-neutral-900">{{ $person->initials() }}</span>
                                @endforeach
                                @if ($people->count() > 4)
                                    <span class="flex size-8 items-center justify-center rounded-full bg-neutral-200 text-xs font-semibold text-neutral-700 ring-2 ring-white dark:bg-neutral-700 dark:text-neutral-200 dark:ring-neutral-900">+{{ $people->count() - 4 }}</span>
                                @endif
                            </span>
                            <span class="text-sm font-medium">{{ __('Dalībnieki') }}</span>
                        </button>
                    </flux:modal.trigger>
                    @if ($isOwner)
                        <flux:modal.trigger name="members">
                            <flux:button icon="plus" size="sm" variant="primary" square aria-label="{{ __('Uzaicināt dalībniekus') }}" />
                        </flux:modal.trigger>
                    @endif
                </div>
            </div>

            @if (session('success'))
                <flux:text class="mt-2 text-emerald-600 dark:text-emerald-400">{{ session('success') }}</flux:text>
            @endif

            @if ($list->products->isEmpty())
                <flux:text class="mt-2">{{ __('Šis saraksts ir tukšs.') }}</flux:text>
            @else
                <div class="mt-6 space-y-3">
                    @foreach ($list->products as $product)
                        <div class="flex items-center justify-between rounded-xl border border-neutral-200 p-4 shadow-sm dark:border-neutral-700">
                            <div>
                                <flux:heading size="sm">{{ $product->title }}</flux:heading>
                                <flux:text>{{ $product->store }}</flux:text>
                            </div>
                            <div class="flex items-center gap-3">
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('cart.lists.items.decrease', [$list, $product]) }}">
                                        @csrf
                                        <button type="submit" class="flex size-8 items-center justify-center rounded-full border border-neutral-300 text-lg transition hover:border-emerald-500 hover:text-emerald-600 dark:border-neutral-600" aria-label="{{ __('Decrease quantity') }}">-</button>
                                    </form>
                                @endif
                                <span class="min-w-6 text-center">{{ $canEdit ? '' : '× ' }}{{ $product->pivot->quantity }}</span>
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('cart.lists.items.store', [$list, $product]) }}">
                                        @csrf
                                        <button type="submit" class="flex size-8 items-center justify-center rounded-full border border-neutral-300 text-lg transition hover:border-emerald-500 hover:text-emerald-600 dark:border-neutral-600" aria-label="{{ __('Increase quantity') }}">+</button>
                                    </form>
                                @endif
                                <flux:heading size="sm">
                                    {{ $product->current_price !== null ? number_format((float) $product->current_price * $product->pivot->quantity, 2) . ' €' : '—' }}
                                </flux:heading>
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('cart.lists.items.destroy', [$list, $product]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-red-600 transition hover:text-red-700" aria-label="{{ __('Remove item') }}">{{ __('Remove') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <flux:modal name="members" :show="$errors->has('email') || session('members_modal')" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Dalībnieki') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ $isOwner
                        ? __('Rediģētāji var pievienot un noņemt preces, skatītāji var tikai skatīt.')
                        : __('Lietotāji, kuriem ir piekļuve šim sarakstam.') }}
                </flux:text>
            </div>

            @if ($isOwner)
                <form method="POST" action="{{ route('cart.members.store', $list) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium">{{ __('Uzaicināt pēc e-pasta') }}</label>
                        <input type="email" name="email" id="email" required value="{{ old('email') }}" placeholder="{{ __('draugs@piemers.lv') }}"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900" />
                        @error('email')
                            <flux:text class="mt-1 text-red-600">{{ $message }}</flux:text>
                        @enderror
                    </div>
                    <div class="flex items-end gap-3">
                        <div class="flex-1">
                            <label for="role" class="mb-1 block text-sm font-medium">{{ __('Loma') }}</label>
                            <select name="role" id="role"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                                <option value="{{ ShoppingList::ROLE_EDITOR }}">{{ __('Rediģētājs') }}</option>
                                <option value="{{ ShoppingList::ROLE_VIEWER }}">{{ __('Skatītājs') }}</option>
                            </select>
                        </div>
                        <flux:button type="submit" variant="primary">{{ __('Uzaicināt') }}</flux:button>
                    </div>
                </form>

                <flux:separator />

                <div class="space-y-3">
                    <div>
                        <flux:heading size="sm">{{ __('Uzaicinājuma saite') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Ikviens ar šo saiti var pievienoties sarakstam.') }}</flux:text>
                    </div>

                    @if ($list->invite_token)
                        <div x-data="{ copied: false }" class="flex items-center gap-2">
                            <input type="text" readonly x-ref="link" value="{{ route('cart.invite.accept', $list->invite_token) }}" aria-label="{{ __('Uzaicinājuma saite') }}"
                                x-on:focus="$el.select()"
                                class="min-w-0 flex-1 rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800" />
                            <flux:button type="button" size="sm"
                                x-on:click="
                                    $refs.link.select();
                                    (navigator.clipboard ? navigator.clipboard.writeText($refs.link.value) : Promise.resolve(document.execCommand('copy')))
                                        .then(() => { copied = true; setTimeout(() => copied = false, 2000) });
                                ">
                                <span x-show="! copied">{{ __('Kopēt') }}</span>
                                <span x-show="copied" style="display: none">{{ __('Nokopēts!') }}</span>
                            </flux:button>
                        </div>
                        <flux:text>
                            {{ __('Pievienojas kā: :role', ['role' => $roleLabels[$list->invite_role] ?? $list->invite_role]) }}
                        </flux:text>
                    @endif

                    <div class="flex flex-wrap items-end gap-3">
                        <form method="POST" action="{{ route('cart.invite.store', $list) }}" class="flex flex-1 items-end gap-3">
                            @csrf
                            <div class="flex-1">
                                <label for="invite_role" class="mb-1 block text-sm font-medium">{{ __('Saites loma') }}</label>
                                <select name="role" id="invite_role"
                                    class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                                    <option value="{{ ShoppingList::ROLE_EDITOR }}" @selected($list->invite_role === ShoppingList::ROLE_EDITOR)>{{ __('Rediģētājs') }}</option>
                                    <option value="{{ ShoppingList::ROLE_VIEWER }}" @selected($list->invite_role === ShoppingList::ROLE_VIEWER)>{{ __('Skatītājs') }}</option>
                                </select>
                            </div>
                            <flux:button type="submit">{{ $list->invite_token ? __('Jauna saite') : __('Izveidot saiti') }}</flux:button>
                        </form>
                        @if ($list->invite_token)
                            <form method="POST" action="{{ route('cart.invite.destroy', $list) }}">
                                @csrf
                                @method('DELETE')
                                <flux:button type="submit" variant="danger">{{ __('Atslēgt') }}</flux:button>
                            </form>
                        @endif
                    </div>
                </div>

                <flux:separator />
            @endif

            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <flux:heading size="sm">{{ $list->user->name }}</flux:heading>
                        <flux:text class="break-all">{{ $list->user->email }}</flux:text>
                    </div>
                    <span class="text-sm font-medium">{{ $roleLabels['owner'] }}</span>
                </div>
                @foreach ($list->members as $member)
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <flux:heading size="sm">{{ $member->name }}</flux:heading>
                            <flux:text class="break-all">{{ $member->email }}</flux:text>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($isOwner)
                                <form method="POST" action="{{ route('cart.members.update', [$list, $member]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <select name="role" onchange="this.form.submit()" aria-label="{{ __('Loma') }}"
                                        class="rounded-lg border border-neutral-300 px-2 py-1.5 text-sm dark:border-neutral-600 dark:bg-neutral-900">
                                        <option value="{{ ShoppingList::ROLE_EDITOR }}" @selected($member->pivot->role === ShoppingList::ROLE_EDITOR)>{{ __('Rediģētājs') }}</option>
                                        <option value="{{ ShoppingList::ROLE_VIEWER }}" @selected($member->pivot->role === ShoppingList::ROLE_VIEWER)>{{ __('Skatītājs') }}</option>
                                    </select>
                                </form>
                                <form method="POST" action="{{ route('cart.members.destroy', [$list, $member]) }}" onsubmit="return confirm('{{ __('Noņemt šo lietotāju no saraksta?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-600 transition hover:text-red-700">{{ __('Noņemt') }}</button>
                                </form>
                            @else
                                <span class="text-sm">{{ $roleLabels[$member->pivot->role] ?? $member->pivot->role }}</span>
                                @if ($member->is(auth()->user()))
                                    <form method="POST" action="{{ route('cart.members.destroy', [$list, $member]) }}" onsubmit="return confirm('{{ __('Pamest šo sarakstu?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-red-600 transition hover:text-red-700">{{ __('Pamest') }}</button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
                @if ($isOwner)
                    @foreach ($list->invitations as $invitation)
                        <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <flux:heading size="sm">{{ $invitation->user->name }}</flux:heading>
                                <flux:text class="break-all">{{ $invitation->user->email }}</flux:text>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">{{ __('Gaida atbildi') }}</span>
                                <form method="POST" action="{{ route('invitations.destroy', $invitation) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-600 transition hover:text-red-700">{{ __('Atsaukt') }}</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </flux:modal>
</x-layouts::app>
