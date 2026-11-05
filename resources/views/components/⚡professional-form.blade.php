<?php

use App\Actions\Professionals\CreateProfessional;
use App\Actions\Professionals\UpdateProfessional;
use App\Data\ProfessionalData;
use App\Exceptions\RoleNotEnabledException;
use App\Models\Professional;
use App\Models\Service;
use App\Models\WorkingHour;
use App\Support\CurrentTenant;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public const int DETAILS = 1;

    public const int SERVICES = 2;

    public const int WORKING_HOURS = 3;

    public const array WEEK = [1, 2, 3, 4, 5, 6, 0];

    #[Locked]
    public ?int $professionalId = null;

    #[Locked]
    public int $step = self::DETAILS;

    /** @var list<int> */
    #[Locked]
    public array $confirmedSteps = [];

    public string $name = '';

    public string $email = '';

    public string $password = '';

    /** @var list<string> */
    public array $serviceIds = [];

    /** @var array<int, list<array{starts_at: string, ends_at: string}>> */
    public array $workingHours = [];

    public ?TemporaryUploadedFile $photo = null;

    public bool $removePhoto = false;

    #[On('open-professional-form')]
    public function open(?int $professionalId = null, int $step = self::DETAILS): void
    {
        $this->authorize($professionalId === null ? 'professionals.create' : 'professionals.update');
        $this->reset();
        $this->resetValidation();

        $professionalId === null ? $this->startCreating() : $this->startEditing($professionalId);

        if ($professionalId !== null) {
            $this->step = max(self::DETAILS, min($step, self::WORKING_HOURS));
            array_map($this->confirmStep(...), range(self::DETAILS, $this->step - 1));
        }

        Flux::modal('professional-form')->show();
    }

    #[Computed]
    public function isEditing(): bool
    {
        return $this->professionalId !== null;
    }

    /** @return list<array{number: int, caption: string, status: string, timeline: string}> */
    #[Computed]
    public function steps(): array
    {
        $steps = [
            self::DETAILS => [__('messages.details'), false],
            self::SERVICES => [__('messages.services'), true],
            self::WORKING_HOURS => [__('messages.working_hours_short'), true],
        ];

        return array_map(function (int $number, array $step) {
            [$label, $optional] = $step;
            $status = $this->stepStatus($number);

            return [
                'number' => $number,
                'caption' => $optional ? $label.' · '.__('messages.optional') : $label,
                'status' => $status,
                'timeline' => match ($status) {
                    'complete', 'current' => $status,
                    default => 'incomplete',
                },
            ];
        }, array_keys($steps), $steps);
    }

    #[Computed]
    public function stepHeading(): string
    {
        return match ($this->step) {
            self::DETAILS => __('messages.professional_details'),
            self::SERVICES => __('messages.services_offered'),
            default => __('messages.working_hours'),
        };
    }

    #[Computed]
    public function stepDescription(): string
    {
        return match ($this->step) {
            self::DETAILS => $this->isEditing ? __('messages.update_basic_professional_details') : __('messages.enter_basic_professional_details'),
            self::SERVICES => __('messages.professional_found_for_selected_services'),
            default => __('messages.use_two_intervals_for_a_break'),
        };
    }

    /** @return Collection<int, Service> */
    #[Computed]
    public function services(): Collection
    {
        return Service::orderBy('name')->get();
    }

    /** @return list<array{day: int, name: string}> */
    #[Computed]
    public function weekDays(): array
    {
        $names = WorkingHour::dayNames();

        return array_map(fn (int $day) => ['day' => $day, 'name' => $names[$day]], self::WEEK);
    }

    #[Computed]
    public function photoUrl(): ?string
    {
        if ($this->photo?->isPreviewable()) {
            return $this->photo->temporaryUrl();
        }

        if ($this->removePhoto || ! $this->isEditing) {
            return null;
        }

        return Professional::findOrFail($this->professionalId)->photo_url;
    }

    public function updatedPhoto(): void
    {
        $this->validateOnly('photo', $this->detailsRules(), attributes: $this->validationAttributes());
        $this->removePhoto = false;
    }

    public function clearPhoto(): void
    {
        $this->photo = null;
        $this->removePhoto = true;
    }

    public function addInterval(int $day): void
    {
        $previous = end($this->workingHours[$day]) ?: null;

        $this->workingHours[$day][] = $previous
            ? ['starts_at' => $previous['ends_at'], 'ends_at' => '']
            : ['starts_at' => '09:00', 'ends_at' => '18:00'];
    }

    public function removeInterval(int $day, int $index): void
    {
        unset($this->workingHours[$day][$index]);

        $this->workingHours[$day] = array_values($this->workingHours[$day]);
    }

    public function continue(): void
    {
        $this->validateStep($this->step);
        $this->confirmStep($this->step);
        $this->step = min($this->step + 1, self::WORKING_HOURS);
    }

    public function back(): void
    {
        $this->resetValidation();
        $this->step = max($this->step - 1, self::DETAILS);
    }

    public function save(CreateProfessional $createProfessional, UpdateProfessional $updateProfessional): void
    {
        $this->authorize($this->isEditing ? 'professionals.update' : 'professionals.create');

        try {
            foreach ([self::DETAILS, self::SERVICES, self::WORKING_HOURS] as $step) {
                $this->validateStep($step);
            }

            $this->confirmStep(self::WORKING_HOURS);

            $this->isEditing
                ? $this->saveChanges($updateProfessional)
                : $this->createProfessional($createProfessional);
        } catch (ValidationException $exception) {
            $this->step = $this->stepWithErrors(array_keys($exception->errors()));

            throw $exception;
        } catch (RoleNotEnabledException $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');

            return;
        }

        Flux::modal('professional-form')->close();
        Flux::toast($this->isEditing ? __('messages.professional_updated') : __('messages.professional_created'), variant: 'success');

        $this->dispatch('professional-saved');
        $this->reset();
    }

    private function startCreating(): void
    {
        $this->workingHours = array_fill(0, 7, []);
    }

    private function startEditing(int $professionalId): void
    {
        $professional = Professional::with(['user', 'services', 'workingHours'])->findOrFail($professionalId);

        $this->professionalId = $professional->id;
        $this->name = $professional->name;
        $this->email = $professional->user->email;
        $this->serviceIds = $professional->services->pluck('id')->map(fn (int $id) => (string) $id)->all();
        $this->workingHours = array_fill(0, 7, []);

        foreach ($professional->workingHours->sortBy('starts_at') as $interval) {
            $this->workingHours[$interval->day_of_week][] = [
                'starts_at' => $interval->starts_at,
                'ends_at' => $interval->ends_at,
            ];
        }
    }

    private function createProfessional(CreateProfessional $createProfessional): void
    {
        $createProfessional->handle($this->professionalData(), $this->password);
    }

    private function saveChanges(UpdateProfessional $updateProfessional): void
    {
        $updateProfessional->handle(Professional::findOrFail($this->professionalId), $this->professionalData(), $this->password ?: null);
    }

    private function professionalData(): ProfessionalData
    {
        return new ProfessionalData(
            name: trim($this->name),
            email: $this->email,
            serviceIds: array_map(intval(...), $this->serviceIds),
            workingHours: $this->flattenedWorkingHours(),
            photo: $this->photo,
            removePhoto: $this->removePhoto,
        );
    }


    private function validateStep(int $step): void
    {
        $rules = match ($step) {
            self::DETAILS => $this->detailsRules(),
            self::SERVICES => $this->servicesRules(),
            default => $this->workingHoursRules(),
        };

        $this->validate($rules, $this->messages(), $this->validationAttributes());
    }

    /** @return array<string, mixed> */
    private function detailsRules(): array
    {
        $userId = $this->isEditing ? Professional::findOrFail($this->professionalId)->user_id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => $this->isEditing ? ['nullable', 'string', Password::defaults()] : ['required', 'string', Password::defaults()],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /** @return array<string, mixed> */
    private function servicesRules(): array
    {
        return [
            'serviceIds' => ['array'],
            'serviceIds.*' => ['integer', Rule::exists('services', 'id')->where('tenant_id', app(CurrentTenant::class)->id())],
        ];
    }

    /** @return array<string, mixed> */
    private function workingHoursRules(): array
    {
        return [
            'workingHours.*.*.starts_at' => ['required', 'date_format:H:i'],
            'workingHours.*.*.ends_at' => ['required', 'date_format:H:i', 'after:workingHours.*.*.starts_at'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('messages.name'),
            'email' => __('messages.email'),
            'password' => __('messages.password'),
            'photo' => __('messages.photo'),
            'serviceIds' => __('messages.services'),
            'workingHours.*.*.starts_at' => __('messages.start'),
            'workingHours.*.*.ends_at' => __('messages.end'),
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'workingHours.*.*.ends_at.after' => __('messages.end_must_be_after_start'),
        ];
    }

    private function confirmStep(int $step): void
    {
        if (! in_array($step, $this->confirmedSteps, true)) {
            $this->confirmedSteps[] = $step;
        }
    }

    private function stepStatus(int $step): string
    {
        return match (true) {
            $step === $this->step => 'current',
            in_array($step, $this->confirmedSteps, true) => 'complete',
            default => 'pending',
        };
    }

    /** @param list<string> $fields */
    private function stepWithErrors(array $fields): int
    {
        foreach ($fields as $field) {
            if (in_array($field, ['name', 'email', 'password', 'photo'], true)) {
                return self::DETAILS;
            }
        }

        foreach ($fields as $field) {
            if (str_starts_with($field, 'serviceIds')) {
                return self::SERVICES;
            }
        }

        return self::WORKING_HOURS;
    }

    /** @return list<array{day_of_week: int, starts_at: string, ends_at: string}> */
    private function flattenedWorkingHours(): array
    {
        $flattened = [];

        foreach ($this->workingHours as $day => $intervals) {
            foreach ($intervals as $interval) {
                $flattened[] = [
                    'day_of_week' => (int) $day,
                    'starts_at' => $interval['starts_at'],
                    'ends_at' => $interval['ends_at'],
                ];
            }
        }

        return $flattened;
    }
};
?>

<div>
    <flux:modal name="professional-form" class="w-full p-0! md:w-4xl md:max-w-4xl" :closable="false" :dismissible="false">
        <div class="flex max-h-[calc(100dvh-4rem)] flex-col">
            <div class="flex items-center gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:avatar icon="user-circle" class="shrink-0 bg-table-header text-table-header-content" />

                <div class="min-w-0 flex-1">
                    <flux:heading size="lg">{{ $this->isEditing ? __('messages.edit_professional') : __('messages.new_professional') }}</flux:heading>
                    <flux:text>{{ $this->isEditing ? __('messages.update_professional_details_services_hours') : __('messages.register_professional_services_and_hours') }}</flux:text>
                </div>

                <flux:modal.close>
                    <flux:button variant="ghost" size="sm" icon="x-mark" :aria-label="__('messages.close')" />
                </flux:modal.close>
            </div>

            <div class="flex min-h-0 flex-1 flex-col md:flex-row">
                <nav class="shrink-0 border-b border-zinc-200 bg-zinc-100 px-6 py-4 md:w-60 md:border-e md:border-b-0 md:px-5 md:py-6 dark:border-zinc-700 dark:bg-black/20" aria-label="{{ __('messages.steps') }}">
                    @foreach ([true, false] as $horizontal)
                        <div @class(['md:hidden' => $horizontal, 'max-md:hidden' => ! $horizontal])>
                            <flux:timeline :horizontal="$horizontal" size="sm">
                                @foreach ($this->steps as $wizardStep)
                                    <flux:timeline.item
                                        :status="$wizardStep['timeline']"
                                        :aria-current="$wizardStep['status'] === 'current' ? 'step' : null"
                                        wire:key="wizard-step-{{ $horizontal ? 'h' : 'v' }}-{{ $wizardStep['number'] }}"
                                    >
                                        <flux:timeline.indicator @class(['border-zinc-300! bg-white! dark:border-zinc-600! dark:bg-zinc-800!' => $wizardStep['status'] === 'pending'])>
                                            @if ($wizardStep['status'] === 'complete')
                                                <flux:icon.check variant="micro" />
                                            @else
                                                {{ $wizardStep['number'] }}
                                            @endif
                                        </flux:timeline.indicator>

                                        <flux:timeline.content>
                                            <flux:heading @class(['font-normal', 'text-zinc-500 dark:text-zinc-400' => $wizardStep['status'] === 'pending'])>
                                                {{ $wizardStep['caption'] }}
                                            </flux:heading>
                                        </flux:timeline.content>
                                    </flux:timeline.item>
                                @endforeach
                            </flux:timeline>
                        </div>
                    @endforeach
                </nav>

                <div class="min-h-0 flex-1 overflow-y-auto p-6">
                    <div class="mb-5">
                        <flux:heading size="lg">{{ $this->stepHeading }}</flux:heading>
                        <flux:text class="mt-1">{{ $this->stepDescription }}</flux:text>
                    </div>

                    @if ($step === $this::DETAILS)
                        <div wire:key="step-details" class="flex flex-col gap-5">
                            <div class="flex items-center gap-4">
                                <div class="relative">
                                    <flux:avatar size="xl" circle :src="$this->photoUrl" :name="$name ?: null" icon="user" class="bg-table-header text-table-header-content" />

                                    <div wire:loading.flex wire:target="photo" class="absolute inset-0 items-center justify-center rounded-full bg-white/75 dark:bg-zinc-900/75" aria-hidden="true">
                                        <flux:icon.loading class="text-table-header-content" />
                                    </div>
                                </div>

                                <div class="flex min-w-0 flex-col items-start gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:file-upload wire:model="photo" accept="image/jpeg,image/png,image/webp">
                                            <flux:button as="div" size="sm" icon="photo">{{ __('messages.choose_photo') }}</flux:button>
                                        </flux:file-upload>

                                        @if ($this->photoUrl)
                                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="clearPhoto">{{ __('messages.remove_photo') }}</flux:button>
                                        @endif
                                    </div>

                                    <flux:text size="sm">{{ __('messages.jpg_png_webp_up_to_2mb') }}</flux:text>
                                    <flux:error name="photo" />
                                </div>
                            </div>

                            <div class="grid gap-5 md:grid-cols-2">
                                <flux:input wire:model.blur="name" :label="__('messages.name')" :placeholder="__('messages.rafael_mendes')" required />

                                <flux:input wire:model="email" :label="__('messages.email')" type="email" autocomplete="off" required />
                            </div>

                            @if ($this->isEditing)
                                <flux:input
                                    wire:model="password"
                                    :label="__('messages.password_optional')"
                                    :placeholder="__('messages.type_a_new_password')"
                                    :description:trailing="__('messages.leave_blank_to_keep_password')"
                                    type="password"
                                    autocomplete="new-password"
                                    viewable
                                />
                            @else
                                <flux:input
                                    wire:model="password"
                                    :label="__('messages.password')"
                                    :description:trailing="__('messages.initial_sign_in_password')"
                                    type="password"
                                    autocomplete="new-password"
                                    viewable
                                    required
                                />
                            @endif
                        </div>
                    @elseif ($step === $this::SERVICES)
                        <div wire:key="step-services" class="flex flex-col gap-4">
                            <flux:pillbox
                                wire:model="serviceIds"
                                multiple
                                searchable
                                :aria-label="__('messages.services_offered')"
                                :placeholder="__('messages.select_the_services')"
                            >
                                @foreach ($this->services as $service)
                                    <flux:pillbox.option :value="(string) $service->id" wire:key="service-option-{{ $service->id }}">
                                        {{ $service->name }}{{ $service->is_active ? '' : ' '.__('messages.inactive_in_parentheses') }}
                                    </flux:pillbox.option>
                                @endforeach
                            </flux:pillbox>

                            <flux:error name="serviceIds.*" />
                        </div>
                    @else
                        <div wire:key="step-working-hours" class="flex flex-col gap-3">

                            <flux:error name="workingHours" />

                            <div class="flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach ($this->weekDays as $weekDay)
                                    <div wire:key="day-{{ $weekDay['day'] }}" class="flex flex-col gap-2 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-start">
                                        <flux:heading class="sm:w-24 sm:shrink-0 sm:pt-2">{{ $weekDay['name'] }}</flux:heading>

                                        <div class="flex flex-1 flex-wrap items-start gap-x-6 gap-y-2">
                                            @forelse ($workingHours[$weekDay['day']] as $index => $interval)
                                                <div wire:key="day-{{ $weekDay['day'] }}-interval-{{ $index }}" class="flex flex-col gap-1">
                                                    <div class="flex items-center gap-2">
                                                        <flux:time-picker wire:model="workingHours.{{ $weekDay['day'] }}.{{ $index }}.starts_at" type="input" :dropdown="false" time-format="24-hour" :aria-label="__('messages.start')" />
                                                        <flux:text>{{ __('messages.to') }}</flux:text>
                                                        <flux:time-picker wire:model="workingHours.{{ $weekDay['day'] }}.{{ $index }}.ends_at" type="input" :dropdown="false" time-format="24-hour" :aria-label="__('messages.end')" />
                                                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeInterval({{ $weekDay['day'] }}, {{ $index }})" :aria-label="__('messages.remove_interval')" />
                                                    </div>

                                                    <flux:error name="workingHours.{{ $weekDay['day'] }}.{{ $index }}.starts_at" />
                                                    <flux:error name="workingHours.{{ $weekDay['day'] }}.{{ $index }}.ends_at" />
                                                </div>
                                            @empty
                                                <flux:badge color="zinc" size="sm" class="sm:mt-2">{{ __('messages.day_off') }}</flux:badge>
                                            @endforelse
                                        </div>

                                        <flux:button size="sm" variant="subtle" icon="plus" class="self-start sm:mt-1" wire:click="addInterval({{ $weekDay['day'] }})">{{ __('messages.interval') }}</flux:button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('messages.cancel') }}</flux:button>
                </flux:modal.close>

                <div class="ms-auto flex flex-wrap items-center justify-end gap-2">
                    @if ($step === $this::DETAILS)
                        <flux:button variant="primary" icon:trailing="arrow-right" wire:click="continue" wire:loading.attr="disabled" wire:target="continue,photo">{{ __('messages.continue') }}</flux:button>
                    @elseif ($step === $this::SERVICES)
                        <flux:button icon="arrow-left" wire:click="back">{{ __('messages.back') }}</flux:button>
                        <flux:button variant="primary" icon:trailing="arrow-right" wire:click="continue" wire:loading.attr="disabled" wire:target="continue">{{ __('messages.continue') }}</flux:button>
                    @else
                        <flux:button icon="arrow-left" wire:click="back" wire:loading.attr="disabled" wire:target="save">{{ __('messages.back') }}</flux:button>
                        <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                            {{ $this->isEditing ? __('messages.save_changes') : __('messages.create_professional') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    </flux:modal>
</div>
