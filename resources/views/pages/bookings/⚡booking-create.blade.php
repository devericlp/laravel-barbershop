<?php

use App\Actions\Bookings\CreateBooking;
use App\Actions\Bookings\CreateCustomerBooking;
use App\Actions\Bookings\CreateGuestBooking;
use App\Actions\Bookings\ListAvailableSlots;
use App\Data\BookingData;
use App\Enums\BookingChannel;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    public const int BARBERSHOP = 0;

    public const int SERVICE = 1;

    public const int DATE = 2;

    public const int PROFESSIONAL = 3;

    public const int TIME = 4;

    public const int CONFIRMATION = 5;

    public const int DONE = 6;

    #[Locked]
    public bool $barbershopFixed = false;

    #[Locked]
    public int $step = self::BARBERSHOP;

    #[Locked]
    public ?int $tenantId = null;

    #[Locked]
    public ?int $serviceId = null;

    #[Locked]
    public ?string $date = null;

    #[Locked]
    public ?int $professionalId = null;

    #[Locked]
    public ?string $time = null;

    #[Locked]
    public ?int $bookingId = null;

    public string $name = '';

    public string $phone = '';

    public function mount(): void
    {
        if (auth()->user()?->needsRoleSelection()) {
            redirect()->setIntendedUrl(request()->fullUrl());
            $this->redirectRoute('select-role', navigate: true);

            return;
        }

        if (request()->routeIs('public-bookings.create') && $this->isCustomer) {
            $this->redirect(route('bookings.create', request()->query()), navigate: true);

            return;
        }

        $tenant = $this->barbershopQuery()->firstWhere('slug', request()->string('barbershop')->toString());

        if ($tenant !== null) {
            $this->tenantId = $tenant->id;
            $this->barbershopFixed = true;
        }

        $this->step = $this->firstStep();
        $this->applyTenant();
        $this->restore(request()->string('booking')->toString());
    }

    public function hydrate(): void
    {
        $this->applyTenant();
    }

    public function rendering(View $view): void
    {
        $view->title(__('messages.book_your_appointment'));

        $view->layout('layouts::public');
    }

    #[Computed]
    public function homeUrl(): ?string
    {
        return $this->user()?->homeUrl();
    }

    #[Computed]
    public function isCustomer(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasUserRole(UserRole::Customer) && $user->can('my-bookings.create');
    }

    #[Computed]
    public function customer(): ?Customer
    {
        return $this->isCustomer && $this->barbershop ? $this->user()?->customer()->first() : null;
    }

    #[Computed]
    public function avatarUrl(): ?string
    {
        return $this->user()?->avatar_url;
    }

    #[Computed]
    public function registerUrl(): ?string
    {
        if (auth()->check() || ! $this->barbershop || ! Tenant::query()->acceptingCustomers()->whereKey($this->barbershop->id)->exists()) {
            return null;
        }

        return route('register', ['barbershop' => $this->barbershop->slug]);
    }

    #[Computed]
    public function signInUrl(): string
    {
        $state = json_encode([
            'barbershop' => $this->tenantId,
            'service' => $this->serviceId,
            'date' => $this->date,
            'professional' => $this->professionalId,
            'time' => $this->time,
        ], JSON_THROW_ON_ERROR);

        return route('login', array_filter([
            'barbershop' => $this->barbershop?->slug,
            'booking' => Crypt::encryptString($state),
        ]));
    }

    /** @return Collection<int, Tenant> */
    #[Computed]
    public function barbershops(): Collection
    {
        return $this->barbershopQuery()->orderBy('name')->get();
    }

    #[Computed]
    public function barbershop(): ?Tenant
    {
        return app(CurrentTenant::class)->get();
    }

    /** @return Collection<int, Service> */
    #[Computed]
    public function services(): Collection
    {
        if (! $this->barbershop) {
            return new Collection;
        }

        return Service::active()
            ->whereHas('professionals', fn (Builder $query) => $query->where('is_active', true)->where('user_id', '!=', auth()->id()))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function service(): ?Service
    {
        return $this->services->firstWhere('id', $this->serviceId);
    }

    /** @return Collection<int, Professional> */
    #[Computed]
    public function candidates(): Collection
    {
        if (! $this->service) {
            return new Collection;
        }

        return Professional::active()
            ->with('user')
            ->where('user_id', '!=', auth()->id())
            ->whereHas('services', fn (Builder $query) => $query->whereKey($this->service->id))
            ->orderByName()
            ->get();
    }

    /** @return array<int, list<string>> */
    #[Computed]
    public function slotsByProfessional(): array
    {
        return $this->date ? $this->slotsOn(CarbonImmutable::parse($this->date)) : [];
    }

    /** @return Collection<int, Professional> */
    #[Computed]
    public function professionals(): Collection
    {
        return $this->candidates
            ->filter(fn (Professional $professional) => ($this->slotsByProfessional[$professional->id] ?? []) !== [])
            ->values();
    }

    #[Computed]
    public function professional(): ?Professional
    {
        return $this->professionals->firstWhere('id', $this->professionalId);
    }

    /** @return list<string> */
    #[Computed]
    public function times(): array
    {
        return $this->professional ? $this->slotsByProfessional[$this->professional->id] ?? [] : [];
    }

    #[Computed]
    public function firstDate(): string
    {
        return CarbonImmutable::today()->toDateString();
    }

    #[Computed]
    public function lastDate(): string
    {
        return BookingChannel::Public->lastBookableDate()?->toDateString() ?? $this->firstDate;
    }

    #[Computed]
    public function unavailableDates(): string
    {
        return collect(CarbonPeriod::create($this->firstDate, $this->lastDate))
            ->map(fn ($day) => CarbonImmutable::parse($day))
            ->filter(fn (CarbonImmutable $day) => collect($this->slotsOn($day))->flatten()->isEmpty())
            ->map(fn (CarbonImmutable $day) => $day->toDateString())
            ->implode(',');
    }

    #[Computed]
    public function dateLabel(): ?string
    {
        return $this->date ? CarbonImmutable::parse($this->date)->translatedFormat(__('messages.weekday_date_format')) : null;
    }

    /** @return list<array{key: string, icon: ?string, label: string, value: ?string}> */
    #[Computed]
    public function summary(): array
    {
        return [
            ['key' => 'service', 'icon' => 'scissors', 'label' => __('messages.service'), 'value' => $this->service?->name],
            ['key' => 'professional', 'icon' => null, 'label' => __('messages.professional'), 'value' => $this->professional?->name],
            ['key' => 'date', 'icon' => 'calendar', 'label' => __('messages.date'), 'value' => $this->dateLabel],
            ['key' => 'time', 'icon' => 'clock', 'label' => __('messages.time'), 'value' => $this->time],
            ['key' => 'duration', 'icon' => 'hourglass', 'label' => __('messages.duration'), 'value' => $this->service?->durationLabel()],
            ['key' => 'price', 'icon' => 'coins', 'label' => __('messages.price'), 'value' => $this->service?->formatted_price],
        ];
    }

    /** @return list<array{number: int, label: string, status: string}> */
    #[Computed]
    public function steps(): array
    {
        $labels = array_filter([
            self::BARBERSHOP => $this->barbershopFixed ? null : __('messages.barbershop'),
            self::SERVICE => __('messages.service'),
            self::DATE => __('messages.date'),
            self::PROFESSIONAL => __('messages.professional'),
            self::TIME => __('messages.time'),
            self::CONFIRMATION => __('messages.confirmation'),
        ]);

        return array_values(array_map(fn (int $step, string $label, int $position) => [
            'number' => $position + 1,
            'label' => $label,
            'status' => match (true) {
                $step < $this->step => 'complete',
                $step === $this->step => 'current',
                default => 'incomplete',
            },
        ], array_keys($labels), $labels, array_keys(array_values($labels))));
    }

    #[Computed]
    public function booking(): ?Booking
    {
        return $this->bookingId ? Booking::with(['service', 'professional.user'])->find($this->bookingId) : null;
    }

    public function selectBarbershop(int $tenantId): void
    {
        if ($this->barbershopFixed || $this->step !== self::BARBERSHOP || ! $this->barbershops->contains('id', $tenantId)) {
            return;
        }

        if ($this->tenantId !== $tenantId) {
            $this->reset('serviceId', 'date', 'professionalId', 'time');
        }

        $this->tenantId = $tenantId;
        $this->applyTenant();
        $this->step = self::SERVICE;
    }

    public function selectService(int $serviceId): void
    {
        if ($this->step !== self::SERVICE || ! $this->services->contains('id', $serviceId)) {
            return;
        }

        $this->serviceId = $serviceId;
        $this->keepValidChoices();
        $this->step = self::DATE;
    }

    public function selectDate(string $date): void
    {
        if ($this->step !== self::DATE || ! $this->isBookableDate($date)) {
            return;
        }

        $previous = $this->date;
        $this->date = $date;
        $this->forgetSelections();

        if ($this->professionals->isEmpty()) {
            $this->date = $previous;
            $this->forgetSelections();
            $this->addError('date', __('messages.no_professional_available_on_this_date'));

            return;
        }

        if ($previous !== $date) {
            $this->time = null;
        }

        $this->keepValidChoices();
        $this->step = self::PROFESSIONAL;
    }

    public function selectProfessional(int $professionalId): void
    {
        if ($this->step !== self::PROFESSIONAL || ! $this->professionals->contains('id', $professionalId)) {
            return;
        }

        $this->professionalId = $professionalId;
        $this->keepValidChoices();
        $this->step = self::TIME;
    }

    public function selectTime(string $time): void
    {
        if ($this->step !== self::TIME) {
            return;
        }

        if (! in_array($time, $this->times, true)) {
            $this->addError('time', __('messages.time_not_available'));

            return;
        }

        $this->time = $time;
        $this->step = self::CONFIRMATION;
    }

    public function goBack(): void
    {
        if ($this->step > $this->firstStep() && $this->step < self::DONE) {
            $this->step--;
        }
    }

    public function confirm(CreateBooking $createBooking, CreateCustomerBooking $createCustomerBooking, CreateGuestBooking $createGuestBooking): void
    {
        if ($this->step !== self::CONFIRMATION || $this->bookingId) {
            return;
        }

        if (! $this->service || ! $this->date || ! $this->professionalId || ! $this->time) {
            $this->step = $this->firstStep();

            return;
        }

        $customer = $this->customer;
        $user = $this->user();

        if ($customer === null) {
            $this->validate();
        }

        $startsAt = CarbonImmutable::parse("{$this->date} {$this->time}");

        try {
            $booking = match (true) {
                $customer !== null => $createBooking->handle(new BookingData($customer->id, $this->professionalId, $this->service->id, $startsAt), BookingChannel::Public),
                $this->isCustomer && $user !== null => $createCustomerBooking->handle($user, trim($this->name), $this->phone, $this->professionalId, $this->service->id, $startsAt),
                default => $createGuestBooking->handle(trim($this->name), $this->phone, $this->professionalId, $this->service->id, $startsAt),
            };
        } catch (ValidationException $exception) {
            if (array_key_exists('time', $exception->errors())) {
                $this->time = null;
                $this->step = self::TIME;
                $this->forgetSelections();
            }

            throw $exception;
        }

        $this->bookingId = $booking->id;
        $this->step = self::DONE;
        unset($this->customer);
    }

    public function startOver(): void
    {
        $this->reset('serviceId', 'date', 'professionalId', 'time', 'bookingId', 'name', 'phone');
        $this->step = self::SERVICE;
        $this->forgetSelections();
    }

    /** @return array<string, list<mixed>> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['required', 'string', function (string $attribute, string $value, Closure $fail) {
                if (! PhoneNumber::isValidMobile($value)) {
                    $fail(__('messages.enter_brazilian_mobile'));
                }
            }],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('messages.full_name'),
            'phone' => __('messages.mobile'),
        ];
    }

    private function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /** @return Builder<Tenant> */
    private function barbershopQuery(): Builder
    {
        return $this->isCustomer
            ? Tenant::query()->acceptingCustomers()
            : Tenant::query()->where('is_active', true);
    }

    private function firstStep(): int
    {
        return $this->barbershopFixed ? self::SERVICE : self::BARBERSHOP;
    }

    private function applyTenant(): void
    {
        $tenant = $this->tenantId ? $this->barbershopQuery()->find($this->tenantId) : null;

        app(CurrentTenant::class)->set($tenant);

        if (! $tenant) {
            $this->tenantId = null;
            $this->barbershopFixed = false;

            if ($this->step > self::BARBERSHOP && $this->step < self::DONE) {
                $this->step = self::BARBERSHOP;
            }
        }

        $this->forgetSelections();
    }

    private function restore(string $token): void
    {
        $state = $this->decodeState($token);

        if ($state === null) {
            return;
        }

        if (is_int($state['barbershop'] ?? null)) {
            $this->selectBarbershop($state['barbershop']);
        }

        if (is_int($state['service'] ?? null)) {
            $this->selectService($state['service']);
        }

        if (is_string($state['date'] ?? null)) {
            $this->selectDate($state['date']);
        }

        if (is_int($state['professional'] ?? null)) {
            $this->selectProfessional($state['professional']);
        }

        if (is_string($state['time'] ?? null)) {
            $this->selectTime($state['time']);
        }

        $this->resetErrorBag();
    }

    /** @return array<string, mixed>|null */
    private function decodeState(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        try {
            $state = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return null;
        }

        return is_array($state) ? $state : null;
    }

    private function isBookableDate(string $date): bool
    {
        return CarbonImmutable::canBeCreatedFromFormat($date, 'Y-m-d')
            && $date >= $this->firstDate
            && $date <= $this->lastDate;
    }

    private function keepValidChoices(): void
    {
        $this->forgetSelections();

        if ($this->date && $this->professionals->isEmpty()) {
            $this->date = null;
            $this->forgetSelections();
        }

        if ($this->professionalId && ! $this->professional) {
            $this->professionalId = null;
            $this->forgetSelections();
        }

        if ($this->time && ! in_array($this->time, $this->times, true)) {
            $this->time = null;
            $this->forgetSelections();
        }
    }

    private function forgetSelections(): void
    {
        unset(
            $this->barbershop, $this->customer, $this->services, $this->service, $this->candidates, $this->slotsByProfessional,
            $this->professionals, $this->professional, $this->times, $this->unavailableDates, $this->dateLabel,
        );
    }

    /** @return array<int, list<string>> */
    private function slotsOn(CarbonImmutable $day): array
    {
        $service = $this->service;

        if (! $service) {
            return [];
        }

        $listAvailableSlots = app(ListAvailableSlots::class);

        return $this->candidates
            ->mapWithKeys(fn (Professional $professional) => [
                $professional->id => $listAvailableSlots->handle($professional, $service, $day, BookingChannel::Public),
            ])
            ->all();
    }
};
?>

<div class="flex min-h-svh flex-col">
    @if ($this->barbershop)
        <flux:header class="border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <flux:brand :href="route('home')" :name="$this->barbershop->name">
                <x-slot:logo class="bg-accent text-accent-foreground">
                    <flux:icon.scissors variant="micro" />
                </x-slot:logo>
            </flux:brand>

            <flux:spacer />

            @if ($this->homeUrl)
                <flux:button :href="$this->homeUrl" icon="x-mark" variant="ghost" square :aria-label="__('messages.close')" />
            @endif
        </flux:header>
    @elseif ($this->homeUrl)
        <flux:button
            :href="$this->homeUrl"
            icon="x-mark"
            variant="ghost"
            square
            :aria-label="__('messages.close')"
            class="fixed! top-3 right-4 z-10 sm:top-5 sm:right-6"
        />
    @endif

    <main class="px-4 py-8 sm:px-6 sm:py-12">
        <div class="mx-auto flex w-full max-w-3xl min-w-0 flex-col gap-8">
            @if ($step < $this::DONE)
                <div class="flex flex-col items-center gap-5 text-center sm:gap-6">
                    <div>
                        <flux:text size="sm" class="font-medium tracking-wide text-accent-content! uppercase">{{ $this->barbershop?->name ?? config('app.name') }}</flux:text>
                        <flux:heading size="xl" level="1" class="mt-1">{{ __('messages.book_your_appointment') }}</flux:heading>
                    </div>

                    <nav class="w-full max-w-2xl" aria-label="{{ __('messages.steps') }}">
                        <flux:timeline horizontal size="sm">
                            @foreach ($this->steps as $bookingStep)
                                <flux:timeline.item
                                    :status="$bookingStep['status']"
                                    :aria-current="$bookingStep['status'] === 'current' ? 'step' : null"
                                    wire:key="booking-step-{{ $bookingStep['number'] }}"
                                >
                                    <flux:timeline.indicator @class([
                                        'border-0! bg-accent! text-accent-foreground!' => $bookingStep['status'] === 'current',
                                        'border-zinc-300! bg-white! dark:border-zinc-600! dark:bg-zinc-800!' => $bookingStep['status'] === 'incomplete',
                                    ])>
                                        @if ($bookingStep['status'] === 'complete')
                                            <flux:icon.check variant="micro" />
                                        @else
                                            {{ $bookingStep['number'] }}
                                        @endif
                                    </flux:timeline.indicator>

                                    <flux:timeline.content>
                                        <flux:heading @class([
                                            'text-xs font-normal sm:text-sm',
                                            'font-semibold!' => $bookingStep['status'] === 'current',
                                            'max-sm:sr-only' => $bookingStep['status'] !== 'current',
                                            'text-accent-content!' => $bookingStep['status'] === 'complete',
                                            'text-zinc-500 dark:text-zinc-400' => $bookingStep['status'] === 'incomplete',
                                        ])>
                                            {{ $bookingStep['label'] }}
                                        </flux:heading>
                                    </flux:timeline.content>
                                </flux:timeline.item>
                            @endforeach
                        </flux:timeline>
                    </nav>
                </div>

                <section class="flex min-w-0 flex-col gap-6">
                    @if ($step === $this::BARBERSHOP)
                        <flux:heading size="xl" level="2" class="text-center text-2xl! font-bold! sm:text-3xl!">{{ __('messages.choose_the_barbershop') }}</flux:heading>

                        @if ($this->barbershops->isEmpty())
                            <flux:callout icon="information-circle" :heading="__('messages.no_barbershops_available')" :text="__('messages.come_back_later')" />
                        @else
                            <flux:radio.group variant="cards" :indicator="false" :aria-label="__('messages.barbershop')" class="grid! grid-cols-1 sm:grid-cols-2">
                                @foreach ($this->barbershops as $barbershop)
                                    <flux:radio
                                        wire:key="barbershop-{{ $barbershop->id }}"
                                        :value="$barbershop->id"
                                        :checked="$tenantId === $barbershop->id"
                                        wire:click="selectBarbershop({{ $barbershop->id }})"
                                        class="min-w-0 cursor-pointer items-center max-sm:p-3!"
                                    >
                                        <div class="flex min-w-0 flex-1 items-center gap-3">
                                            <flux:avatar icon="building-storefront" class="size-11! shrink-0 rounded-lg! bg-table-header! text-table-header-content!" />
                                            <flux:heading class="truncate">{{ $barbershop->name }}</flux:heading>
                                        </div>

                                        <flux:icon.check-circle variant="solid" class="hidden size-6 shrink-0 text-accent in-data-checked:block" />
                                    </flux:radio>
                                @endforeach
                            </flux:radio.group>
                        @endif
                    @elseif ($step === $this::SERVICE)
                        <flux:heading size="xl" level="2" class="text-center text-2xl! font-bold! sm:text-3xl!">{{ __('messages.choose_a_service') }}</flux:heading>

                        @if ($this->services->isEmpty())
                            <flux:callout icon="information-circle" :heading="__('messages.no_services_available')" :text="__('messages.come_back_later')" />
                        @else
                            <flux:radio.group variant="cards" :indicator="false" :aria-label="__('messages.service')" class="grid! grid-cols-1 sm:grid-cols-2">
                                @foreach ($this->services as $service)
                                    <flux:radio
                                        wire:key="service-{{ $service->id }}"
                                        :value="$service->id"
                                        :checked="$serviceId === $service->id"
                                        wire:click="selectService({{ $service->id }})"
                                        class="min-w-0 cursor-pointer items-center max-sm:p-3!"
                                    >
                                        <div class="flex min-w-0 flex-1 items-center gap-3">
                                            @if ($service->image_url)
                                                <img src="{{ $service->image_url }}" alt="" class="size-11 shrink-0 rounded-lg object-cover" />
                                            @else
                                                <flux:avatar icon="scissors" class="size-11! shrink-0 rounded-lg! bg-table-header! text-table-header-content!" />
                                            @endif

                                            <div class="min-w-0 flex-1">
                                                <flux:heading class="truncate">{{ $service->name }}</flux:heading>
                                                <flux:text size="sm">{{ $service->formatted_price }} · {{ $service->durationLabel() }}</flux:text>
                                            </div>
                                        </div>

                                        <flux:icon.check-circle variant="solid" class="hidden size-6 shrink-0 text-accent in-data-checked:block" />
                                    </flux:radio>
                                @endforeach
                            </flux:radio.group>
                        @endif
                    @elseif ($step === $this::DATE)
                        <div class="text-center">
                            <flux:heading size="xl" level="2" class="text-2xl! font-bold! sm:text-3xl!">{{ __('messages.choose_the_date') }}</flux:heading>
                            <flux:text class="mt-2">{{ $this->service?->name }} · {{ $this->service?->durationLabel() }}</flux:text>
                        </div>

                        <flux:error name="date" class="text-center" />

                        @foreach (['lg' => 'sm:hidden', 'xl' => 'max-sm:hidden'] as $calendarSize => $visibility)
                            <div
                                wire:key="calendar-{{ $calendarSize }}"
                                class="{{ $visibility }} flex justify-center"
                                x-on:click="const day = $event.target.closest('[data-date]'); if (day && ! day.hasAttribute('data-unavailable') && ! day.hasAttribute('data-outside')) $wire.selectDate(day.dataset.date)"
                            >
                                <flux:calendar
                                    :size="$calendarSize"
                                    :value="$date"
                                    :min="$this->firstDate"
                                    :max="$this->lastDate"
                                    :unavailable="$this->unavailableDates"
                                />
                            </div>
                        @endforeach
                    @elseif ($step === $this::PROFESSIONAL)
                        <div class="text-center">
                            <flux:heading size="xl" level="2" class="text-2xl! font-bold! sm:text-3xl!">{{ __('messages.choose_a_professional') }}</flux:heading>
                            <flux:text class="mt-2 first-letter:uppercase">{{ __('messages.available_on_date', ['date' => $this->dateLabel]) }}</flux:text>
                        </div>

                        @if ($this->professionals->isEmpty())
                            <flux:callout icon="information-circle" :heading="__('messages.no_professional_available_on_this_date')" :text="__('messages.choose_another_date')" />
                        @else
                            <flux:radio.group variant="cards" :indicator="false" :aria-label="__('messages.professional')" class="flex-col">
                                @foreach ($this->professionals as $professional)
                                    <flux:radio
                                        wire:key="professional-{{ $professional->id }}"
                                        :value="$professional->id"
                                        :checked="$professionalId === $professional->id"
                                        wire:click="selectProfessional({{ $professional->id }})"
                                        class="min-w-0 cursor-pointer items-center max-sm:p-3!"
                                    >
                                        <div class="flex min-w-0 flex-1 items-center gap-3 sm:gap-4">
                                            <flux:avatar size="lg" circle :src="$professional->photo_url" :name="$professional->name" class="shrink-0" />
                                            <flux:heading class="truncate">{{ $professional->name }}</flux:heading>
                                        </div>

                                        <flux:icon.check-circle variant="solid" class="hidden size-6 shrink-0 text-accent in-data-checked:block" />
                                        <span class="size-5 shrink-0 rounded-full border border-zinc-300 in-data-checked:hidden dark:border-zinc-600" aria-hidden="true"></span>
                                    </flux:radio>
                                @endforeach
                            </flux:radio.group>
                        @endif
                    @elseif ($step === $this::TIME)
                        <div class="text-center">
                            <flux:heading size="xl" level="2" class="text-2xl! font-bold! sm:text-3xl!">{{ __('messages.choose_the_time') }}</flux:heading>
                            <flux:text class="mt-2">{{ $this->professional?->name }} - <span class="inline-block first-letter:uppercase">{{ $this->dateLabel }}</span></flux:text>
                        </div>

                        <flux:error name="time" class="text-center" />

                        @if ($this->times === [])
                            <flux:text class="text-center">{{ __('messages.no_times_available_on_this_date') }}</flux:text>
                        @else
                            <flux:radio.group variant="pills" :aria-label="__('messages.time')" class="flex-wrap justify-center">
                                @foreach ($this->times as $slot)
                                    <flux:radio
                                        wire:key="slot-{{ $date }}-{{ $slot }}"
                                        :value="$slot"
                                        :label="$slot"
                                        :checked="$time === $slot"
                                        wire:click="selectTime('{{ $slot }}')"
                                        class="cursor-pointer px-4! py-2! tabular-nums"
                                    />
                                @endforeach
                            </flux:radio.group>
                        @endif
                    @else
                        <flux:heading size="xl" level="2" class="text-center text-2xl! font-bold! sm:text-3xl!">{{ __('messages.review_your_booking') }}</flux:heading>

                        <flux:error name="professionalId" class="text-center" />

                        <flux:card class="flex min-w-0 flex-col gap-6 max-sm:p-4! md:flex-row md:gap-8">
                            <div class="flex min-w-0 flex-1 flex-col gap-5">
                                <flux:heading size="lg" level="3" class="font-bold!">{{ __('messages.booking_summary') }}</flux:heading>

                                <dl class="flex flex-col gap-4">
                                    @foreach ($this->summary as $item)
                                        <div wire:key="summary-{{ $item['key'] }}" class="flex min-w-0 items-center gap-3">
                                            @if ($item['icon'])
                                                <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-sand-200 text-accent-content dark:bg-zinc-700">
                                                    <flux:icon :name="$item['icon']" variant="outline" class="size-6" />
                                                </div>
                                            @else
                                                <flux:avatar circle :src="$this->professional?->photo_url" :name="$item['value']" class="size-11! shrink-0" />
                                            @endif

                                            <div class="flex min-w-0 flex-col-reverse">
                                                <dt><flux:text size="sm">{{ $item['label'] }}</flux:text></dt>
                                                <dd><flux:heading class="truncate first-letter:uppercase">{{ $item['value'] }}</flux:heading></dd>
                                            </div>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>

                            <flux:separator vertical class="max-md:hidden" />
                            <flux:separator class="md:hidden" />

                            <div class="flex min-w-0 flex-1 flex-col gap-5">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-sand-200 text-accent-content dark:bg-zinc-700">
                                        <flux:icon.user class="size-6" />
                                    </div>
                                    <flux:heading size="lg" level="3" class="font-bold!">{{ __('messages.your_details') }}</flux:heading>
                                </div>

                                @if ($this->customer)
                                    <div class="flex min-w-0 flex-wrap items-center gap-4 rounded-xl bg-sand-100 p-4 dark:bg-zinc-700/50">
                                        <flux:avatar size="lg" circle :src="$this->avatarUrl" :name="$this->customer->name" class="shrink-0" />

                                        <div class="min-w-0 flex-1">
                                            <flux:heading size="lg" class="truncate">{{ $this->customer->name }}</flux:heading>
                                            <flux:text class="tabular-nums">{{ $this->customer->formatted_phone }}</flux:text>
                                        </div>

                                        <flux:badge color="green" size="sm" rounded class="shrink-0">
                                            <span class="me-1.5 size-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                            {{ __('messages.connected') }}
                                        </flux:badge>
                                    </div>
                                @else
                                    <flux:input wire:model="name" :label="__('messages.full_name')" :placeholder="__('messages.enter_your_full_name')" autocomplete="name" required />
                                    <flux:input wire:model="phone" :label="__('messages.mobile')" type="tel" mask="(99) 99999-9999" :placeholder="__('messages.phone_placeholder')" autocomplete="tel" required />

                                    @guest
                                        <flux:text>
                                            {{ __('messages.already_have_an_account') }}
                                            <flux:link :href="$this->signInUrl">{{ __('messages.log_in') }}</flux:link>
                                        </flux:text>
                                    @endguest
                                @endif
                            </div>
                        </flux:card>

                        <flux:text size="sm" class="text-center">{{ $this->isCustomer ? __('messages.payment_at_barbershop') : __('messages.payment_made_at_barbershop') }}</flux:text>
                    @endif

                    @if ($step > $this->firstStep())
                        <div class="flex flex-wrap items-center gap-3">
                            <flux:button icon="arrow-left" class="max-sm:flex-1" wire:click="goBack" wire:loading.attr="disabled" wire:target="confirm">
                                {{ __('messages.back') }}
                            </flux:button>

                            @if ($step === $this::CONFIRMATION)
                                <flux:spacer class="max-sm:hidden" />

                                <flux:button variant="primary" icon="check" class="max-sm:flex-1" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm">
                                    {{ __('messages.confirm_booking') }}
                                </flux:button>
                            @endif
                        </div>
                    @endif
                </section>
            @elseif ($this->booking)
                <flux:card class="mx-auto flex w-full max-w-lg min-w-0 flex-col items-center gap-6 py-10 text-center max-sm:px-4! max-sm:py-8!">
                    <div class="flex size-12 items-center justify-center rounded-full bg-accent text-accent-foreground">
                        <flux:icon.check />
                    </div>

                    <div>
                        <flux:heading size="xl" level="1">{{ __('messages.booking_confirmed') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('messages.see_you_at_barbershop') }}</flux:text>
                    </div>

                    <dl class="grid w-full gap-4 text-left sm:grid-cols-2">
                        <div>
                            <flux:text size="sm">{{ __('messages.barbershop') }}</flux:text>
                            <flux:heading>{{ $this->barbershop?->name }}</flux:heading>
                        </div>
                        <div>
                            <flux:text size="sm">{{ __('messages.service') }}</flux:text>
                            <flux:heading>{{ $this->booking->service->name }}</flux:heading>
                        </div>
                        <div>
                            <flux:text size="sm">{{ __('messages.professional') }}</flux:text>
                            <flux:heading>{{ $this->booking->professional->name }}</flux:heading>
                        </div>
                        <div>
                            <flux:text size="sm">{{ __('messages.date_and_time') }}</flux:text>
                            <flux:heading class="first-letter:uppercase">{{ $this->dateLabel }}, {{ $time }}</flux:heading>
                        </div>
                        <div>
                            <flux:text size="sm">{{ __('messages.price') }}</flux:text>
                            <flux:heading>{{ $this->booking->formatted_price }}</flux:heading>
                        </div>
                    </dl>

                    <div class="flex w-full flex-col justify-center gap-2 sm:w-auto sm:flex-row">
                        <flux:button variant="filled" icon="plus" wire:click="startOver">{{ __('messages.make_another_booking') }}</flux:button>
                        @if ($this->isCustomer)
                            @can('my-bookings.index')
                                <flux:button variant="primary" icon="calendar-days" :href="route('my-bookings.index')" wire:navigate>{{ __('messages.my_bookings') }}</flux:button>
                            @endcan
                        @endif
                    </div>
                </flux:card>
            @endif

            @if ($this->registerUrl)
                <flux:text class="text-center">
                    {{ __('messages.dont_have_an_account') }}
                    <flux:link :href="$this->registerUrl" wire:navigate>{{ __('messages.create_account') }}</flux:link>
                </flux:text>
            @endif
        </div>
    </main>
</div>
