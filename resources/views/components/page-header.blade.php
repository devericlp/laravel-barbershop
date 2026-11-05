@props([
    'title',
    'subtitle' => null,
    'icon' => null,
])

<div class="flex flex-wrap items-end justify-between gap-4">
    <div class="flex items-start gap-3">
        @if ($icon)
            <flux:avatar :icon="$icon" class="mt-0.5 shrink-0 bg-table-header text-table-header-content" />
        @endif

        <div>
            <flux:heading size="xl" level="1">{{ $title }}</flux:heading>
            @if ($subtitle)
                <flux:text class="mt-1 first-letter:uppercase">{{ $subtitle }}</flux:text>
            @endif
        </div>
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
