@props([
    'heading',
    'icon' => 'magnifying-glass',
    'description' => null,
    'filtering' => false,
])

<div class="flex flex-col items-center gap-2 px-4 py-16 text-center">
    <flux:icon :icon="$icon" class="text-zinc-400" />
    <flux:heading>{{ $heading }}</flux:heading>

    @if ($filtering)
        <flux:text>{{ __('messages.adjust_search_or_filters') }}</flux:text>
        <flux:button size="sm" class="mt-2" wire:click="clearFilters">{{ __('messages.clear_filters') }}</flux:button>
    @elseif ($description)
        <flux:text>{{ $description }}</flux:text>
    @endif
</div>
