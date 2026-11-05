<flux:menu>
    <flux:menu.item icon="user-circle" :href="route('profile.edit')" wire:navigate>{{ __('messages.profile') }}</flux:menu.item>

    <flux:menu.separator />

    <form method="POST" action="{{ route('logout') }}">
        @csrf

        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
            {{ __('messages.log_out') }}
        </flux:menu.item>
    </form>
</flux:menu>
