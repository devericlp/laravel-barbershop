@props(['bookingId', 'barbershop', 'service', 'professional', 'when', 'price', 'status', 'cancellable' => false])

<flux:card size="sm" {{ $attributes->class('flex flex-wrap items-center justify-between gap-4') }}>
    <div class="flex min-w-0 flex-col gap-1">
        <flux:text size="sm" class="font-medium text-accent-content!">{{ $barbershop }}</flux:text>
        <flux:heading>{{ $service }} · {{ $professional }}</flux:heading>
        <flux:text>{{ $when }} · {{ $price }}</flux:text>
    </div>

    <div class="flex items-center gap-2">
        <flux:badge size="sm" :color="$status->color()">{{ $status->label() }}</flux:badge>

        @if ($cancellable)
            <flux:button size="sm" variant="ghost" icon="x-circle" wire:click="cancel({{ $bookingId }})" wire:confirm="{{ __('messages.cancel_booking_slot_released') }}">{{ __('messages.cancel') }}</flux:button>
        @endif
    </div>
</flux:card>
