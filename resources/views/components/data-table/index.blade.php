@props([
    'searchPlaceholder',
    'activeFilters' => [],
])

<div x-data="{ filtersOpen: false }" {{ $attributes->class('flex flex-col gap-3') }}>
    <div class="flex items-center gap-3">
        <form wire:submit="applySearch" role="search" class="flex min-w-0 flex-1 items-center gap-3">
            <flux:input
                wire:model="searchInput"
                icon="magnifying-glass"
                :placeholder="$searchPlaceholder"
                :aria-label="$searchPlaceholder"
                class="min-w-0 flex-1"
            />

            <flux:button type="submit" variant="primary" class="shrink-0">
                <span class="max-sm:hidden">{{ __('messages.search') }}</span>
                <flux:icon.magnifying-glass variant="mini" class="sm:hidden" />
                <span class="sr-only sm:hidden">{{ __('messages.search') }}</span>
            </flux:button>
        </form>

        {{ $toolbar ?? '' }}

        @isset($filters)
            <flux:button x-on:click="filtersOpen = ! filtersOpen" x-bind:aria-expanded="filtersOpen" aria-controls="data-table-filters" class="shrink-0">
                <span class="flex items-center gap-2">
                    <flux:icon.funnel variant="mini" class="text-zinc-400" />
                    <span class="max-sm:sr-only">{{ __('messages.filters') }}</span>
                    @if ($activeFilters !== [])
                        <flux:badge size="sm" color="emerald" class="tabular-nums">{{ count($activeFilters) }}</flux:badge>
                    @endif
                    <flux:icon.chevron-down variant="micro" class="transition-transform duration-200" x-bind:class="filtersOpen && 'rotate-180'" />
                </span>
            </flux:button>
        @endisset
    </div>

    @isset($filters)
        <div id="data-table-filters" x-show="filtersOpen" x-collapse x-cloak>
            <form wire:submit="applyFilters" class="grid gap-4 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-zinc-700 dark:bg-zinc-900">
                {{ $filters }}

                <div class="flex items-end">
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto">{{ __('messages.filter') }}</flux:button>
                </div>
            </form>
        </div>
    @endisset

    @if ($activeFilters !== [])
        <div
            x-data="filterBadges(@js(__('messages.count_filters')))"
            class="relative flex items-center gap-2 overflow-hidden"
        >
            <div x-ref="list" class="flex min-w-0 items-center gap-2 overflow-hidden">
                @foreach ($activeFilters as $filter)
                    <flux:badge wire:key="filter-{{ $filter['key'] }}" data-key="{{ $filter['key'] }}" x-show="! hiddenKeys.includes($el.dataset.key)" size="sm" color="emerald" class="shrink-0">
                        {{ $filter['label'] }}
                        <flux:badge.close wire:click="removeFilter('{{ $filter['key'] }}')" :aria-label="__('messages.remove_filter').': '.$filter['label']" />
                    </flux:badge>
                @endforeach
            </div>

            <flux:badge x-ref="moreProbe" size="sm" class="pointer-events-none invisible absolute" aria-hidden="true" x-text="moreLabel(total)" />

            <div x-show="hiddenKeys.length > 0" x-cloak class="shrink-0">
                <flux:dropdown position="bottom" align="start">
                    <flux:button size="xs" variant="filled" x-text="moreLabel(hiddenKeys.length)" />

                    <flux:popover class="flex max-w-72 flex-col items-start gap-2">
                        @foreach ($activeFilters as $filter)
                            <flux:badge wire:key="hidden-filter-{{ $filter['key'] }}" data-key="{{ $filter['key'] }}" x-show="hiddenKeys.includes($el.dataset.key)" size="sm" color="emerald" class="max-w-full">
                                <span class="truncate">{{ $filter['label'] }}</span>
                                <flux:badge.close wire:click="removeFilter('{{ $filter['key'] }}')" :aria-label="__('messages.remove_filter').': '.$filter['label']" />
                            </flux:badge>
                        @endforeach
                    </flux:popover>
                </flux:dropdown>
            </div>

            <flux:button x-ref="clear" size="xs" variant="ghost" class="shrink-0" wire:click="clearFilters">{{ __('messages.clear_filters') }}</flux:button>
        </div>
    @endif

    <section class="relative mt-3 overflow-hidden rounded-xl border border-emerald-200 bg-white dark:border-emerald-900 dark:bg-zinc-900">
        <div
            wire:loading.delay.class="opacity-50 pointer-events-none"
            class="relative overflow-x-auto transition-opacity [&_[data-flux-column]]:text-table-header-content [&_[data-flux-columns]]:bg-table-header [&_[data-flux-column]]:border-zinc-200 dark:[&_[data-flux-column]]:border-zinc-700 [&_[data-flux-cell]]:border-zinc-100 dark:[&_[data-flux-cell]]:border-white/5 [&_[data-flux-row]]:transition-colors [&_[data-flux-row]:hover]:bg-zinc-50 dark:[&_[data-flux-row]:hover]:bg-white/5 [&_[data-flux-column]:first-child]:ps-4 [&_[data-flux-column]:last-child]:pe-4 [&_[data-flux-cell]:first-child]:ps-4 [&_[data-flux-cell]:last-child]:pe-4"
        >
            {{ $slot }}
        </div>

        {{ $footer ?? '' }}
    </section>
</div>
