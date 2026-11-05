@props(['label', 'value', 'icon' => null, 'hint' => null])

<flux:card size="sm" {{ $attributes->class('flex flex-col gap-2') }}>
    <div class="flex items-center justify-between gap-2">
        <flux:text>{{ $label }}</flux:text>

        @if ($icon)
            <flux:icon :name="$icon" variant="micro" class="text-emerald-500" />
        @endif
    </div>

    <flux:heading size="xl">{{ $value }}</flux:heading>

    @if ($hint)
        <flux:text size="sm">{{ $hint }}</flux:text>
    @endif
</flux:card>
