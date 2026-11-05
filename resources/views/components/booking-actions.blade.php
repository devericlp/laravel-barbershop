@props(['bookingId', 'scheduled' => false, 'inlineEdit' => false])

@if ($scheduled)
    <div class="inline-flex items-center gap-1">
        @if ($inlineEdit)
            @can('bookings.update')
                <flux:button {{ $attributes->merge(['variant' => 'ghost', 'size' => 'sm']) }} icon="pencil-square" x-on:click="$dispatch('open-booking-form', { bookingId: {{ $bookingId }} })" :aria-label="__('messages.edit')" />
            @endcan
        @endif

        @canany(['bookings.update', 'bookings.complete', 'bookings.no-show', 'bookings.cancel'])
            <flux:dropdown position="bottom" align="end">
                <flux:button {{ $attributes->merge(['variant' => 'ghost', 'size' => 'sm']) }} icon="ellipsis-horizontal" :aria-label="__('messages.actions')" />

                <flux:menu>
                    @if (! $inlineEdit)
                        @can('bookings.update')
                            <flux:menu.item icon="pencil-square" x-on:click="$dispatch('open-booking-form', { bookingId: {{ $bookingId }} })">{{ __('messages.edit') }}</flux:menu.item>
                        @endcan
                    @endif
                    @can('bookings.complete')
                        <flux:menu.item icon="check-circle" wire:click="complete({{ $bookingId }})">{{ __('messages.complete') }}</flux:menu.item>
                    @endcan
                    @can('bookings.no-show')
                        <flux:menu.item icon="user-minus" wire:click="markAsNoShow({{ $bookingId }})">{{ __('messages.no_show') }}</flux:menu.item>
                    @endcan
                    @can('bookings.cancel')
                        <flux:menu.separator />
                        <flux:menu.item icon="x-circle" variant="danger" wire:click="cancel({{ $bookingId }})" wire:confirm="{{ __('messages.cancel_booking_slot_freed') }}">{{ __('messages.cancel') }}</flux:menu.item>
                    @endcan
                </flux:menu>
            </flux:dropdown>
        @endcanany
    </div>
@endif
