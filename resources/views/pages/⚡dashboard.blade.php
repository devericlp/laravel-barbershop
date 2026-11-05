<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /** @var array{start?: string|null, end?: string|null} */
    public array $range = [];

    public function mount(): void
    {
        $this->range = [
            'start' => CarbonImmutable::today()->startOfMonth()->toDateString(),
            'end' => CarbonImmutable::today()->endOfMonth()->toDateString(),
        ];
    }

    public function rendering(View $view): void
    {
        $view->title(__('messages.dashboard'));
    }

    #[Computed]
    public function from(): CarbonImmutable
    {
        return $this->parseDate($this->range['start'] ?? null) ?? CarbonImmutable::today()->startOfMonth();
    }

    #[Computed]
    public function until(): CarbonImmutable
    {
        $end = $this->parseDate($this->range['end'] ?? null) ?? $this->from->endOfMonth();

        return $end->max($this->from)->addDay()->startOfDay();
    }

    /** @return array<string, int> */
    #[Computed]
    public function totals(): array
    {
        $counts = $this->periodBookings()
            ->selectRaw('status, count(*) as total, sum(price_cents) as value')
            ->groupBy('status')
            ->get()
            ->keyBy(fn (Booking $row) => $row->status->value);

        $count = fn (BookingStatus $status): int => (int) ($counts[$status->value]->total ?? 0);
        $value = fn (BookingStatus $status): int => (int) ($counts[$status->value]->value ?? 0);

        return [
            'bookings' => (int) $counts->sum('total'),
            'completed' => $count(BookingStatus::Completed),
            'expectedValue' => $value(BookingStatus::Scheduled) + $value(BookingStatus::Completed),
            'completedValue' => $value(BookingStatus::Completed),
            'cancelled' => $count(BookingStatus::Cancelled),
            'noShow' => $count(BookingStatus::NoShow),
        ];
    }

    /** @return list<array{name: string, total: int, percent: int}> */
    #[Computed]
    public function topServices(): array
    {
        return $this->ranking('service');
    }

    /** @return list<array{name: string, total: int, percent: int}> */
    #[Computed]
    public function topProfessionals(): array
    {
        return $this->ranking('professional');
    }

    /** @return list<array{date: string, bookings: int}> */
    #[Computed]
    public function chart(): array
    {
        $perDay = $this->periodBookings()
            ->selectRaw('date(starts_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $points = [];

        foreach (CarbonPeriod::create($this->from, $this->until->subDay()) as $day) {
            $date = $day->format('Y-m-d');
            $points[] = ['date' => $date, 'bookings' => (int) ($perDay[$date] ?? 0)];
        }

        return $points;
    }

    /** @return Collection<int, Booking> */
    #[Computed]
    public function upcoming(): Collection
    {
        return Booking::with(['customer', 'service', 'professional'])
            ->withStatus(BookingStatus::Scheduled)
            ->where('starts_at', '>=', CarbonImmutable::now())
            ->orderBy('starts_at')
            ->limit(8)
            ->get();
    }

    public function money(int $cents): string
    {
        return Money::format($cents);
    }

    /** @return Builder<Booking> */
    private function periodBookings(): Builder
    {
        return Booking::query()->startingBetween($this->from, $this->until);
    }

    /** @return list<array{name: string, total: int, percent: int}> */
    private function ranking(string $relation): array
    {
        $rows = $this->periodBookings()
            ->occupying()
            ->selectRaw("{$relation}_id, count(*) as total")
            ->groupBy("{$relation}_id")
            ->orderByDesc('total')
            ->limit(5)
            ->with($relation)
            ->get();

        $max = (int) ($rows->max('total') ?: 1);

        return $rows->map(fn (Booking $row) => [
            'name' => (string) $row->{$relation}?->name,
            'total' => (int) $row->getAttribute('total'),
            'percent' => (int) round($row->getAttribute('total') / $max * 100),
        ])->values()->all();
    }

    private function parseDate(?string $date): ?CarbonImmutable
    {
        return $date && CarbonImmutable::canBeCreatedFromFormat($date, 'Y-m-d')
            ? CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay()
            : null;
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('messages.dashboard')" :subtitle="__('messages.metrics_by_appointment_date')">
        <x-slot:actions>
            <flux:date-picker
                wire:model.live="range"
                mode="range"
                with-presets
                presets="today thisWeek last7Days thisMonth lastMonth last30Days thisYear"
                class="w-full sm:w-72"
            />
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-stat-card :label="__('messages.bookings')" icon="calendar-days" :value="$this->totals['bookings']" />
        <x-stat-card :label="__('messages.completed_appointments')" icon="check-circle" :value="$this->totals['completed']" />
        <x-stat-card :label="__('messages.expected_value')" icon="banknotes" :value="$this->money($this->totals['expectedValue'])" :hint="__('messages.scheduled_plus_completed')" />
        <x-stat-card :label="__('messages.completed_services_value')" icon="currency-dollar" :value="$this->money($this->totals['completedValue'])" :hint="__('messages.not_a_payment_record')" />
        <x-stat-card :label="__('messages.cancellations')" icon="x-circle" :value="$this->totals['cancelled']" />
        <x-stat-card :label="__('messages.no_shows')" icon="user-minus" :value="$this->totals['noShow']" />
    </div>

    <flux:card class="flex flex-col gap-4">
        <flux:heading>{{ __('messages.bookings_per_day') }}</flux:heading>

        <flux:chart wire:key="chart-{{ $this->from->toDateString() }}-{{ $this->until->toDateString() }}" :value="$this->chart" class="aspect-[3/1] min-h-48">
            <flux:chart.svg>
                <flux:chart.bar field="bookings" class="text-accent" />

                <flux:chart.axis axis="x" field="date">
                    <flux:chart.axis.tick />
                    <flux:chart.axis.line />
                </flux:chart.axis>

                <flux:chart.axis axis="y" tick-count="4">
                    <flux:chart.axis.grid />
                    <flux:chart.axis.tick />
                </flux:chart.axis>
            </flux:chart.svg>

            <flux:chart.tooltip>
                <flux:chart.tooltip.heading field="date" :format="['weekday' => 'short', 'day' => 'numeric', 'month' => 'short']" />
                <flux:chart.tooltip.value field="bookings" :label="__('messages.bookings')" />
            </flux:chart.tooltip>
        </flux:chart>
    </flux:card>

    <div class="grid gap-4 lg:grid-cols-3">
        @foreach ([__('messages.most_booked_services') => $this->topServices, __('messages.most_booked_professionals') => $this->topProfessionals] as $title => $ranking)
            <flux:card wire:key="ranking-{{ $loop->index }}" class="flex flex-col gap-4">
                <flux:heading>{{ $title }}</flux:heading>

                @forelse ($ranking as $item)
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center justify-between gap-2">
                            <flux:text variant="strong">{{ $item['name'] }}</flux:text>
                            <flux:text>{{ $item['total'] }}</flux:text>
                        </div>
                        <flux:progress :value="$item['percent']" />
                    </div>
                @empty
                    <flux:text>{{ __('messages.no_bookings_in_period') }}</flux:text>
                @endforelse
            </flux:card>
        @endforeach

        <flux:card class="flex flex-col gap-4">
            <div class="flex items-center justify-between gap-2">
                <flux:heading>{{ __('messages.upcoming_appointments') }}</flux:heading>
                @can('bookings.index')
                    <flux:button size="sm" variant="ghost" :href="route('bookings.index')" wire:navigate>{{ __('messages.view_schedule') }}</flux:button>
                @endcan
            </div>

            @forelse ($this->upcoming as $booking)
                <div wire:key="upcoming-{{ $booking->id }}" class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <flux:text variant="strong" class="truncate">{{ $booking->customer->name }}</flux:text>
                        <flux:text size="sm" class="truncate">{{ $booking->service->name }} · {{ $booking->professional->name }}</flux:text>
                    </div>
                    <flux:badge size="sm" class="shrink-0">{{ $booking->shortStart() }}</flux:badge>
                </div>
            @empty
                <flux:text>{{ __('messages.no_upcoming_appointments') }}</flux:text>
            @endforelse
        </flux:card>
    </div>
</div>
