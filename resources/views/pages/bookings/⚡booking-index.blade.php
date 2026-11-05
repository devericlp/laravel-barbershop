<?php

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\CompleteBooking;
use App\Actions\Bookings\MarkBookingAsNoShow;
use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Exceptions\BookingNotScheduledException;
use App\Livewire\DataTableComponent;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;

new class extends DataTableComponent
{
    public const array SORTABLE = ['starts_at', 'customer', 'price_cents', 'status'];

    public const array TOGGLEABLE_COLUMNS = ['service', 'professional', 'price', 'status'];

    #[Url]
    public string $view = 'day';

    #[Url]
    public string $date = '';

    #[Url(as: 'sort')]
    public string $sortBy = 'starts_at';

    #[Url(as: 'direction')]
    public string $sortDirection = 'asc';

    /** @var list<string> */
    #[Session]
    public array $columns = self::TOGGLEABLE_COLUMNS;

    public function mount(): void
    {
        if (! in_array($this->view, ['day', 'week'], true)) {
            $this->view = 'day';
        }

        if (! $this->isValidDate($this->date)) {
            $this->date = CarbonImmutable::today()->toDateString();
        }

        $this->sanitizeListing();
    }

    public function rendering(View $view): void
    {
        $view->title(__('messages.schedule'));
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['view', 'date', 'columns'], true) || str_starts_with($property, 'columns.')) {
            $this->sanitizeListing();
            $this->resetPage();
        }
    }

    #[Computed]
    public function seesAllProfessionals(): bool
    {
        return ! auth()->user()?->hasUserRole(UserRole::Professional);
    }

    #[Computed]
    public function ownProfessionalId(): ?int
    {
        return $this->seesAllProfessionals ? null : auth()->user()?->professional?->id;
    }

    #[Computed]
    public function periodStart(): CarbonImmutable
    {
        $date = CarbonImmutable::parse($this->date);

        return $this->view === 'week' ? $date->startOfWeek(CarbonImmutable::SUNDAY) : $date->startOfDay();
    }

    #[Computed]
    public function periodEnd(): CarbonImmutable
    {
        return $this->view === 'week' ? $this->periodStart->addWeek() : $this->periodStart->addDay();
    }

    #[Computed]
    public function periodLabel(): string
    {
        if ($this->view === 'day') {
            return $this->periodStart->translatedFormat(__('messages.weekday_date_year_format'));
        }

        $last = $this->periodEnd->subDay();

        return $this->periodStart->translatedFormat(__('messages.day_month_format')).' – '.$last->translatedFormat(__('messages.day_month_year_format'));
    }

    /** @return Collection<int, Professional> */
    #[Computed]
    public function professionals(): Collection
    {
        return Professional::with('user')->orderByName()->get();
    }

    /** @return Collection<int, Service> */
    #[Computed]
    public function services(): Collection
    {
        return Service::orderBy('name')->get();
    }

    /** @return list<BookingStatus> */
    #[Computed]
    public function statusOptions(): array
    {
        return BookingStatus::cases();
    }

    /** @return LengthAwarePaginator<int, Booking> */
    #[Computed]
    public function bookings(): LengthAwarePaginator
    {
        return $this->sorted($this->filteredQuery())->paginate($this->perPage);
    }

    /** @return list<array{date: string, weekday: string, day: string, isToday: bool, bookings: Collection<int, Booking>}> */
    #[Computed]
    public function weekDays(): array
    {
        $bookings = $this->filteredQuery()->orderBy('starts_at')->get();
        $days = [];

        foreach (CarbonPeriod::create($this->periodStart, $this->periodEnd->subDay()) as $day) {
            $day = CarbonImmutable::parse($day);

            $days[] = [
                'date' => $day->toDateString(),
                'weekday' => $day->translatedFormat('D'),
                'day' => $day->format('d'),
                'isToday' => $day->isToday(),
                'bookings' => $bookings->filter(fn (Booking $booking) => $booking->starts_at->isSameDay($day))->values(),
            ];
        }

        return $days;
    }

    /** @return array<string, array{label: string, options: array<string, string>, multiple?: bool}> */
    protected function filterDefinitions(): array
    {
        $definitions = [];

        if ($this->seesAllProfessionals) {
            $definitions['professional'] = [
                'label' => __('messages.professional'),
                'options' => $this->professionals->mapWithKeys(fn (Professional $professional) => [(string) $professional->id => $professional->name])->all(),
            ];
        }

        $definitions['service'] = [
            'label' => __('messages.service'),
            'options' => $this->services->mapWithKeys(fn (Service $service) => [(string) $service->id => $service->name])->all(),
        ];

        $definitions['status'] = [
            'label' => __('messages.status'),
            'options' => collect($this->statusOptions)->mapWithKeys(fn (BookingStatus $status) => [$status->value => $status->label()])->all(),
            'multiple' => true,
        ];

        return $definitions;
    }

    public function sort(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
        $this->resetPage();
    }

    public function previous(): void
    {
        $this->date = CarbonImmutable::parse($this->date)->sub($this->view === 'week' ? '1 week' : '1 day')->toDateString();
        $this->resetPage();
    }

    public function next(): void
    {
        $this->date = CarbonImmutable::parse($this->date)->add($this->view === 'week' ? '1 week' : '1 day')->toDateString();
        $this->resetPage();
    }

    public function today(): void
    {
        $this->date = CarbonImmutable::today()->toDateString();
        $this->resetPage();
    }

    public function showDay(string $date): void
    {
        $this->date = $date;
        $this->view = 'day';
        $this->resetPage();
    }

    #[On('booking-saved')]
    public function refreshBookings(?string $date = null): void
    {
        if ($date && $this->isValidDate($date)) {
            $this->date = $date;
        }

        unset($this->bookings, $this->weekDays);
    }

    public function complete(int $bookingId, CompleteBooking $completeBooking): void
    {
        $this->changeStatus('complete', fn (Booking $booking) => $completeBooking->handle($booking), $bookingId, __('messages.appointment_completed'));
    }

    public function markAsNoShow(int $bookingId, MarkBookingAsNoShow $markBookingAsNoShow): void
    {
        $this->changeStatus('markAsNoShow', fn (Booking $booking) => $markBookingAsNoShow->handle($booking), $bookingId, __('messages.no_show_registered'));
    }

    public function cancel(int $bookingId, CancelBooking $cancelBooking): void
    {
        $this->changeStatus('cancel', fn (Booking $booking) => $cancelBooking->handle($booking), $bookingId, __('messages.booking_cancelled_slot_freed'));
    }

    private function changeStatus(string $ability, Closure $action, int $bookingId, string $message): void
    {
        $booking = $this->visibleBookings()->findOrFail($bookingId);

        $this->authorize($ability, $booking);

        try {
            $action($booking);
            Flux::toast($message, variant: 'success');
        } catch (BookingNotScheduledException $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        }

        unset($this->bookings, $this->weekDays);
    }

    /** @return Builder<Booking> */
    private function visibleBookings(): Builder
    {
        return Booking::query()
            ->when(! $this->seesAllProfessionals, fn (Builder $query) => $query->where('professional_id', $this->ownProfessionalId ?? 0));
    }

    /** @return Builder<Booking> */
    private function filteredQuery(): Builder
    {
        $search = $this->searchTerm() ?? '';
        $digits = PhoneNumber::normalize($search);
        $professionalId = $this->seesAllProfessionals ? $this->appliedFilter('professional') : null;
        $serviceId = $this->appliedFilter('service');
        $statuses = $this->appliedFilterValues('status');

        return $this->visibleBookings()
            ->with(['customer', 'service', 'professional'])
            ->startingBetween($this->periodStart, $this->periodEnd)
            ->when($professionalId, fn (Builder $query) => $query->where('professional_id', (int) $professionalId))
            ->when($serviceId, fn (Builder $query) => $query->where('service_id', (int) $serviceId))
            ->when($statuses !== [], fn (Builder $query) => $query->whereIn('status', $statuses))
            ->when($search !== '', fn (Builder $query) => $query->whereHas('customer', fn (Builder $customer) => $customer
                ->where('name', 'like', "%{$search}%")
                ->when($digits !== '', fn (Builder $phone) => $phone->orWhere('phone', 'like', "%{$digits}%"))));
    }

    /**
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    private function sorted(Builder $query): Builder
    {
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        if ($this->sortBy === 'customer') {
            return $query->orderBy(
                Customer::select('name')->whereColumn('customers.id', 'bookings.customer_id'),
                $direction,
            )->orderBy('starts_at');
        }

        return $query->orderBy($this->sortBy, $direction)->orderBy('starts_at');
    }

    private function sanitizeListing(): void
    {
        $this->columns = array_values(array_intersect($this->columns, self::TOGGLEABLE_COLUMNS));

        if (! in_array($this->sortBy, self::SORTABLE, true)) {
            $this->sortBy = 'starts_at';
        }
    }

    private function isValidDate(string $date): bool
    {
        return CarbonImmutable::canBeCreatedFromFormat($date, 'Y-m-d');
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('messages.schedule')" :subtitle="$this->periodLabel">
        @can('bookings.create')
            <x-slot:actions>
                <flux:button variant="primary" icon="plus" x-on:click="$dispatch('open-booking-form', { date: '{{ $date }}' })">{{ __('messages.new_booking') }}</flux:button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div class="flex flex-wrap items-center gap-3">
        <flux:button.group>
            <flux:button icon="chevron-left" wire:click="previous" :aria-label="__('messages.previous')" />
            <flux:button wire:click="today">{{ __('messages.today') }}</flux:button>
            <flux:button icon="chevron-right" wire:click="next" :aria-label="__('messages.next')" />
        </flux:button.group>

        <flux:date-picker wire:model.live="date" class="w-44" />

        <flux:radio.group wire:model.live="view" variant="segmented" class="w-auto">
            <flux:radio value="day" :label="__('messages.day')" />
            <flux:radio value="week" :label="__('messages.week')" />
        </flux:radio.group>
    </div>

    <x-data-table :search-placeholder="__('messages.search_by_customer_or_mobile')" :active-filters="$this->activeFilters">
        @if ($view === 'day')
            <x-slot:toolbar>
                <flux:dropdown position="bottom" align="end">
                    <flux:button icon="view-columns" class="shrink-0" :aria-label="__('messages.columns')">
                        <span class="max-sm:hidden">{{ __('messages.columns') }}</span>
                    </flux:button>

                    <flux:menu class="min-w-48">
                        <flux:menu.checkbox.group wire:model.live="columns">
                            @foreach ($this::TOGGLEABLE_COLUMNS as $column)
                                <flux:menu.checkbox :value="$column">{{ __('messages.'.$column) }}</flux:menu.checkbox>
                            @endforeach
                        </flux:menu.checkbox.group>
                    </flux:menu>
                </flux:dropdown>
            </x-slot:toolbar>
        @endif

        <x-slot:filters>
            @if ($this->seesAllProfessionals)
                <flux:select wire:model="filters.professional" :label="__('messages.professional')">
                    <flux:select.option value="">{{ __('messages.all') }}</flux:select.option>
                    @foreach ($this->professionals as $professional)
                        <flux:select.option :value="(string) $professional->id" wire:key="filter-professional-{{ $professional->id }}">{{ $professional->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select wire:model="filters.service" :label="__('messages.service')">
                <flux:select.option value="">{{ __('messages.all') }}</flux:select.option>
                @foreach ($this->services as $service)
                    <flux:select.option :value="(string) $service->id" wire:key="filter-service-{{ $service->id }}">{{ $service->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="filters.status" variant="listbox" multiple :label="__('messages.status')" :placeholder="__('messages.all')">
                @foreach ($this->statusOptions as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </x-slot:filters>

        @if ($view === 'day')
            @if ($this->bookings->isEmpty())
                <x-data-table.empty
                    :heading="$this->activeFilters !== [] ? __('messages.no_bookings_found') : __('messages.no_bookings_on_day')"
                    :description="__('messages.use_new_booking_to_register')"
                    icon="calendar-days"
                    :filtering="$this->activeFilters !== []"
                />
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$sortBy === 'starts_at'" :direction="$sortDirection" wire:click="sort('starts_at')">{{ __('messages.time') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortBy === 'customer'" :direction="$sortDirection" wire:click="sort('customer')">{{ __('messages.customer') }}</flux:table.column>
                        @if (in_array('service', $columns, true))
                            <flux:table.column>{{ __('messages.service') }}</flux:table.column>
                        @endif
                        @if (in_array('professional', $columns, true))
                            <flux:table.column>{{ __('messages.professional') }}</flux:table.column>
                        @endif
                        @if (in_array('price', $columns, true))
                            <flux:table.column align="end" sortable :sorted="$sortBy === 'price_cents'" :direction="$sortDirection" wire:click="sort('price_cents')">{{ __('messages.price') }}</flux:table.column>
                        @endif
                        @if (in_array('status', $columns, true))
                            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">{{ __('messages.status') }}</flux:table.column>
                        @endif
                        <flux:table.column align="end"><span class="sr-only">{{ __('messages.actions') }}</span></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->bookings as $booking)
                            <flux:table.row :key="$booking->id">
                                <flux:table.cell class="py-3">
                                    <div class="flex flex-col">
                                        <flux:text variant="strong" class="font-medium">{{ $booking->timeRange() }}</flux:text>
                                        <flux:text size="sm">{{ __('messages.count_minutes', ['count' => $booking->duration_minutes]) }}</flux:text>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex flex-col">
                                        <flux:text variant="strong" class="font-medium">{{ $booking->customer->name }}</flux:text>
                                        <flux:text size="sm">{{ $booking->customer->formatted_phone }}</flux:text>
                                    </div>
                                </flux:table.cell>
                                @if (in_array('service', $columns, true))
                                    <flux:table.cell>{{ $booking->service->name }}</flux:table.cell>
                                @endif
                                @if (in_array('professional', $columns, true))
                                    <flux:table.cell>
                                        <div class="flex items-center gap-2">
                                            <flux:avatar size="xs" circle :src="$booking->professional->photo_url" :name="$booking->professional->name" />
                                            <span>{{ $booking->professional->name }}</span>
                                        </div>
                                    </flux:table.cell>
                                @endif
                                @if (in_array('price', $columns, true))
                                    <flux:table.cell align="end" class="tabular-nums">{{ $booking->formatted_price }}</flux:table.cell>
                                @endif
                                @if (in_array('status', $columns, true))
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="$booking->status->color()" inset="top bottom">{{ $booking->status->label() }}</flux:badge>
                                    </flux:table.cell>
                                @endif
                                <flux:table.cell align="end">
                                    <x-booking-actions :booking-id="$booking->id" :scheduled="$booking->isScheduled()" inline-edit inset="top bottom" />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                <x-slot:footer>
                    <x-data-table.pagination
                        :from="$this->bookings->firstItem()"
                        :to="$this->bookings->lastItem()"
                        :total="$this->bookings->total()"
                        :current-page="$this->bookings->currentPage()"
                        :last-page="$this->bookings->lastPage()"
                    />
                </x-slot:footer>
            @endif
        @else
            <div class="grid gap-3 p-4 lg:grid-cols-7">
                @foreach ($this->weekDays as $day)
                    <div wire:key="week-{{ $day['date'] }}" class="flex flex-col gap-2 rounded-lg border border-zinc-200 bg-zinc-50 p-2 dark:border-zinc-700 dark:bg-zinc-800">
                        <flux:button variant="ghost" size="sm" class="justify-between" wire:click="showDay('{{ $day['date'] }}')">
                            <span class="capitalize">{{ $day['weekday'] }}</span>
                            <flux:badge size="sm" :color="$day['isToday'] ? 'green' : 'zinc'">{{ $day['day'] }}</flux:badge>
                        </flux:button>

                        @forelse ($day['bookings'] as $booking)
                            <flux:card wire:key="week-booking-{{ $booking->id }}" size="sm" class="flex flex-col gap-1 p-2!">
                                <div class="flex items-start justify-between gap-1">
                                    <flux:text size="sm" variant="strong">{{ $booking->timeRange() }}</flux:text>
                                    <x-booking-actions :booking-id="$booking->id" :scheduled="$booking->isScheduled()" size="xs" inset="top right" />
                                </div>
                                <flux:text size="sm" class="truncate">{{ $booking->customer->name }}</flux:text>
                                <flux:text size="sm" class="truncate">{{ $booking->service->name }} · {{ $booking->professional->name }}</flux:text>
                                <div>
                                    <flux:badge size="sm" :color="$booking->status->color()">{{ $booking->status->label() }}</flux:badge>
                                </div>
                            </flux:card>
                        @empty
                            <flux:text size="sm" class="px-2 py-1">{{ $this->activeFilters !== [] ? __('messages.no_matches') : __('messages.free') }}</flux:text>
                        @endforelse
                    </div>
                @endforeach
            </div>
        @endif
    </x-data-table>

    @canany(['bookings.create', 'bookings.update'])
        <livewire:booking-form />
    @endcanany
</div>
