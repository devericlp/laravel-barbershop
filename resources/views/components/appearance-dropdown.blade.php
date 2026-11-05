@props(['position' => 'bottom', 'align' => 'end'])

<flux:dropdown x-data :position="$position" :align="$align" {{ $attributes }}>
    <flux:button variant="ghost" size="sm" square :aria-label="__('messages.theme')">
        <flux:icon.sun variant="mini" x-show="$flux.appearance === 'light'" />
        <flux:icon.moon variant="mini" x-show="$flux.appearance === 'dark'" x-cloak />
        <flux:icon.computer-desktop variant="mini" x-show="$flux.appearance === 'system'" x-cloak />
    </flux:button>

    <flux:menu>
        <flux:menu.radio.group x-model="$flux.appearance">
            <flux:menu.radio value="light" icon="sun">{{ __('messages.light') }}</flux:menu.radio>
            <flux:menu.radio value="dark" icon="moon">{{ __('messages.dark') }}</flux:menu.radio>
            <flux:menu.radio value="system" icon="computer-desktop">{{ __('messages.system') }}</flux:menu.radio>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
