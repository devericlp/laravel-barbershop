<?php

use App\Actions\Bookings\CreateBooking;
use App\Actions\Bookings\ListAvailableSlots;
use App\Actions\Bookings\UpdateBooking;
use App\Data\BookingData;
use App\Enums\BookingChannel;
use App\Exceptions\BookingNotScheduledException;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Support\CurrentTenant;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public ?int $bookingId = null;

    public ?int $customerId = null;

    public string $customerSearch = '';

    public ?int $serviceId = null;

    public ?int $professionalId = null;

    public ?string $date = null;

    public ?string $time = null;

    public string $notes = '';

    #[On('open-booking-form')]
    public function open(?int $bookingId = null, ?string $date = null): void
    {
        $this->authorize($bookingId === null ? 'bookings.create' : 'bookings.update');
        $this->reset();
        $this->resetValidation();
        $this->date = $date ?? CarbonImmutable::today()->toDateString();

        if ($bookingId !== null) {
            $this->fill($this->formStateFor(Booking::with('customer')->findOrFail($bookingId)));
        }

        Flux::modal('booking-form')->show();
    }

    #[Computed]
    public function canCreateCustomer(): bool
    {
        return (bool) auth()->user()?->can('customers.create');
    }

    /** @return Collection<int, Customer> */
    #[Computed]
    public function customers(): Collection
    {
        $search = trim($this->customerSearch);
        $digits = PhoneNumber::normalize($search);

        $customers = Customer::query()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->when($digits !== '', fn (Builder $phone) => $phone->orWhere('phone', 'like', "%{$digits}%"))))
            ->orderByName()
            ->limit(20)
            ->get();

        $selected = $this->customerId ? Customer::find($this->customerId) : null;

        return $selected && ! $customers->contains($selected) ? $customers->prepend($selected) : $customers;
    }

    #[On('customer-created')]
    public function selectCustomer(int $customerId): void
    {
        $this->customerId = $customerId;
        $this->customerSearch = '';
    }

    /** @return Collection<int, Service> */
    #[Computed]
    public function services(): Collection
    {
        return Service::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhere('id', $this->editingBooking()?->service_id))
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Professional> */
    #[Computed]
    public function professionals(): Collection
    {
        if (! $this->serviceId) {
            return new Collection;
        }

        return Professional::active()
            ->whereHas('services', fn (Builder $query) => $query->whereKey($this->serviceId))
            ->orderByName()
            ->get();
    }

    /** @return list<string> */
    #[Computed]
    public function times(): array
    {
        $service = $this->serviceId ? Service::find($this->serviceId) : null;
        $professional = $this->professionalId ? Professional::find($this->professionalId) : null;

        if (! $service || ! $professional || ! $this->date) {
            return [];
        }

        $times = app(ListAvailableSlots::class)->handle(
            $professional,
            $service,
            CarbonImmutable::parse($this->date),
            BookingChannel::Admin,
            $this->editingBooking(),
        );

        return $this->time && ! in_array($this->time, $times, true) && $this->keepsOriginalSlot()
            ? collect([...$times, $this->time])->sort()->values()->all()
            : $times;
    }

    public function updatedServiceId(): void
    {
        if (! $this->professionals->contains('id', $this->professionalId)) {
            $this->professionalId = null;
        }

        $this->time = null;
    }

    public function updatedProfessionalId(): void
    {
        $this->time = null;
    }

    public function updatedDate(): void
    {
        $this->time = null;
    }

    public function save(CreateBooking $createBooking, UpdateBooking $updateBooking): void
    {
        $this->authorize($this->bookingId === null ? 'bookings.create' : 'bookings.update');

        $this->validate([
            'customerId' => ['required', 'integer', Rule::exists('customers', 'id')->where('tenant_id', app(CurrentTenant::class)->id())],
            'serviceId' => ['required', 'integer'],
            'professionalId' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'customerId' => __('messages.customer'),
            'serviceId' => __('messages.service'),
            'professionalId' => __('messages.professional'),
            'date' => __('messages.date'),
            'time' => __('messages.time'),
            'notes' => __('messages.notes'),
        ]);

        $data = new BookingData(
            customerId: (int) $this->customerId,
            professionalId: (int) $this->professionalId,
            serviceId: (int) $this->serviceId,
            startsAt: CarbonImmutable::parse("{$this->date} {$this->time}"),
            notes: trim($this->notes) ?: null,
        );

        try {
            $booking = $this->bookingId
                ? $updateBooking->handle(Booking::findOrFail($this->bookingId), $data)
                : $createBooking->handle($data, BookingChannel::Admin);
        } catch (BookingNotScheduledException $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');

            return;
        }

        Flux::modal('booking-form')->close();
        Flux::toast($this->bookingId ? __('messages.booking_updated') : __('messages.booking_created'), variant: 'success');

        $this->dispatch('booking-saved', date: $booking->starts_at->toDateString());
    }

    private function editingBooking(): ?Booking
    {
        return $this->bookingId ? Booking::find($this->bookingId) : null;
    }

    private function keepsOriginalSlot(): bool
    {
        $booking = $this->editingBooking();

        return $booking !== null
            && $booking->professional_id === $this->professionalId
            && $booking->service_id === $this->serviceId
            && $booking->starts_at->format('Y-m-d H:i') === "{$this->date} {$this->time}";
    }

    /** @return array<string, mixed> */
    private function formStateFor(Booking $booking): array
    {
        return [
            'bookingId' => $booking->id,
            'customerId' => $booking->customer_id,
            'serviceId' => $booking->service_id,
            'professionalId' => $booking->professional_id,
            'date' => $booking->starts_at->toDateString(),
            'time' => $booking->starts_at->format('H:i'),
            'notes' => $booking->notes ?? '',
        ];
    }
};
?>

<div>
    <flux:modal name="booking-form" class="w-full p-0! md:w-2xl md:max-w-2xl" :closable="false">
        <form wire:submit="save" class="flex max-h-[calc(100dvh-4rem)] flex-col">
            <div class="flex items-center gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:avatar icon="calendar-days" class="shrink-0 bg-table-header text-table-header-content" />

                <div class="min-w-0 flex-1">
                    <flux:heading size="lg">{{ $bookingId ? __('messages.edit_booking') : __('messages.new_booking') }}</flux:heading>
                    <flux:text>{{ $bookingId ? __('messages.change_booking_details') : __('messages.choose_customer_service_and_time') }}</flux:text>
                </div>

                <flux:modal.close>
                    <flux:button variant="ghost" size="sm" icon="x-mark" :aria-label="__('messages.close')" />
                </flux:modal.close>
            </div>

            <div class="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto p-6">
                <div class="flex items-end gap-2">
                    <flux:select wire:model="customerId" variant="combobox" :filter="false" :label="__('messages.customer')" :placeholder="__('messages.search_by_name_or_phone')" class="flex-1">
                        <x-slot name="input">
                            <flux:select.input wire:model.live.debounce.300ms="customerSearch" />
                        </x-slot>

                        @forelse ($this->customers as $customer)
                            <flux:select.option :value="$customer->id" wire:key="customer-{{ $customer->id }}">{{ $customer->name }} · {{ $customer->formatted_phone }}</flux:select.option>
                        @empty
                            <flux:select.option.empty>{{ __('messages.no_customers_found') }}</flux:select.option.empty>
                        @endforelse
                    </flux:select>

                    @if ($this->canCreateCustomer)
                        <flux:button icon="user-plus" x-on:click="$dispatch('open-customer-form')">{{ __('messages.new_customer') }}</flux:button>
                    @endif
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="serviceId" :label="__('messages.service')" :placeholder="__('messages.select')">
                        @foreach ($this->services as $service)
                            <flux:select.option :value="$service->id">{{ $service->name }} · {{ $service->formatted_price }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="professionalId" :label="__('messages.professional')" :placeholder="__('messages.select')" :disabled="! $serviceId">
                        @foreach ($this->professionals as $professional)
                            <flux:select.option :value="$professional->id">{{ $professional->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:date-picker wire:model.live="date" :label="__('messages.date')" />

                    <flux:select wire:model="time" :label="__('messages.time')" :placeholder="__('messages.select')" :disabled="$this->times === []">
                        @foreach ($this->times as $slot)
                            <flux:select.option :value="$slot">{{ $slot }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                @if ($serviceId && $professionalId && $date && $this->times === [])
                    <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('messages.no_free_times_for_professional')" />
                @endif

                <flux:textarea wire:model="notes" :label="__('messages.notes')" :badge="__('messages.optional')" rows="2" />
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('messages.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('messages.save_booking') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    @if ($this->canCreateCustomer)
        <livewire:customer-form />
    @endif
</div>
