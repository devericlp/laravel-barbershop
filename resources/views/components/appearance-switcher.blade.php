<flux:radio.group x-data variant="segmented" x-model="$flux.appearance" size="sm" {{ $attributes }} :aria-label="__('messages.theme')">
    <flux:radio value="light" icon="sun" :aria-label="__('messages.light')" />
    <flux:radio value="dark" icon="moon" :aria-label="__('messages.dark')" />
    <flux:radio value="system" icon="computer-desktop" :aria-label="__('messages.system')" />
</flux:radio.group>
