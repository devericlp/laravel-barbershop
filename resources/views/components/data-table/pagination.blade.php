<div class="flex flex-col gap-3 border-t border-zinc-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700">
    <flux:text size="sm" class="tabular-nums">
        {{ __('messages.showing_from_to_of_total', ['from' => $from, 'to' => $to, 'total' => $total]) }}
    </flux:text>

    <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2">
            <flux:text size="sm">{{ __('messages.per_page') }}</flux:text>
            <flux:select wire:model.live="perPage" size="sm" class="w-20" :aria-label="__('messages.per_page')">
                @foreach ($perPageOptions() as $option)
                    <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($lastPage > 1)
            <nav class="flex items-center gap-1" aria-label="{{ __('messages.pagination') }}">
                <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="previousPage" :disabled="$currentPage === 1" :aria-label="__('messages.previous')" />

                @foreach ($links() as $page)
                    @if ($page === null)
                        <flux:text size="sm" class="px-1">…</flux:text>
                    @else
                        <flux:button
                            wire:key="page-{{ $page }}"
                            size="sm"
                            :variant="$page === $currentPage ? 'filled' : 'ghost'"
                            wire:click="gotoPage({{ $page }})"
                            :aria-current="$page === $currentPage ? 'page' : null"
                            class="tabular-nums"
                        >{{ $page }}</flux:button>
                    @endif
                @endforeach

                <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="nextPage" :disabled="$currentPage === $lastPage" :aria-label="__('messages.next')" />
            </nav>
        @endif
    </div>
</div>
