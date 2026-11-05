<?php

use App\Actions\Bookings\CancelCustomerBooking;
use App\Exceptions\BookingNotScheduledException;
use App\Exceptions\CancellationDeadlinePassedException;
use App\Models\Booking;
use App\Models\Scopes\TenantScope;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public function rendering(View $view): void
    {
        $view->title(__('messages.my_bookings'));
    }

    #[Computed]
    public function cancellationNoticeHours(): int
    {
        return intdiv(config()->integer('booking.cancellation_notice_minutes'), 60);
    }

    /** @return Collection<int, Booking> */
    #[Computed]
    public function upcoming(): Collection
    {
        return $this->ownBookings()
            ->where('starts_at', '>=', CarbonImmutable::now())
            ->orderBy('starts_at')
            ->get();
    }

    /** @return Collection<int, Booking> */
    #[Computed]
    public function past(): Collection
    {
        return $this->ownBookings()
            ->where('starts_at', '<', CarbonImmutable::now())
            ->orderByDesc('starts_at')
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function canCancel(): bool
    {
        return (bool) auth()->user()?->can('my-bookings.cancel');
    }

    public function cancel(int $bookingId, CancelCustomerBooking $cancelCustomerBooking): void
    {
        $booking = $this->ownBookings()->findOrFail($bookingId);

        $this->authorize('cancelOwn', $booking);

        try {
            $cancelCustomerBooking->handle($booking);
            Flux::toast(__('messages.booking_cancelled'), variant: 'success');
        } catch (BookingNotScheduledException|CancellationDeadlinePassedException $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        }

        unset($this->upcoming, $this->past);
    }

    /** @return Builder<Booking> */
    private function ownBookings(): Builder
    {
        $withoutTenantScope = fn (Relation $query) => $query->withoutGlobalScope(TenantScope::class);

        return Booking::withoutGlobalScope(TenantScope::class)
            ->with(['tenant', 'service' => $withoutTenantScope, 'professional' => $withoutTenantScope])
            ->whereIn('customer_id', auth()->user()?->customers()->select('id'));
    }
};
?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-8">
    <x-page-header :title="__('messages.my_bookings')" :subtitle="__('messages.cancel_up_to_hours_before', ['hours' => $this->cancellationNoticeHours])">
        @can('my-bookings.create')
            <x-slot:actions>
                <flux:button variant="primary" icon="plus" :href="route('bookings.create')" wire:navigate>{{ __('messages.book') }}</flux:button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <section class="flex flex-col gap-3">
        <flux:heading size="lg">{{ __('messages.upcoming') }}</flux:heading>

        @forelse ($this->upcoming as $booking)
            <x-my-booking-card :booking-id="$booking->id" :service="$booking->service->name" :professional="$booking->professional->name" :barbershop="$booking->tenant->name" :when="$booking->shortStart()" :price="$booking->formatted_price" :status="$booking->status" :cancellable="$booking->isScheduled() && $this->canCancel" wire:key="upcoming-{{ $booking->id }}" />
        @empty
            <flux:card class="flex flex-col items-center gap-2 py-10 text-center">
                <flux:icon.calendar-days class="text-zinc-400" />
                <flux:heading>{{ __('messages.no_upcoming_bookings') }}</flux:heading>
                <flux:text>{{ __('messages.use_book_to_reserve') }}</flux:text>
            </flux:card>
        @endforelse
    </section>

    @if ($this->past->isNotEmpty())
        <section class="flex flex-col gap-3">
            <flux:heading size="lg">{{ __('messages.past') }}</flux:heading>

            @foreach ($this->past as $booking)
                <x-my-booking-card :booking-id="$booking->id" :service="$booking->service->name" :professional="$booking->professional->name" :barbershop="$booking->tenant->name" :when="$booking->shortStart()" :price="$booking->formatted_price" :status="$booking->status" wire:key="past-{{ $booking->id }}" />
            @endforeach
        </section>
    @endif
</div>
