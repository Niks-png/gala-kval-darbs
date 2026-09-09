<x-layouts::app :title="__('Cart')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div>
            <flux:heading size="xl">{{ __('Cart') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Your cart is empty.') }}</flux:text>
        </div>
    </div>
</x-layouts::app>
