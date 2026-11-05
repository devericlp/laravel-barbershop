<?php

use App\Actions\Professionals\DeleteProfessional;
use App\Actions\Professionals\UpdateProfessionalStatus;
use App\Enums\UserRole;
use App\Exceptions\CannotDeleteRecordException;
use App\Livewire\DataTableComponent;
use App\Models\Professional;
use App\Models\Service;
use App\Models\WorkingHour;
use App\Support\CurrentTenant;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

new class extends DataTableComponent
{
    public ?int $deletingId = null;

    #[Locked]
    public ?int $statusChangingId = null;

    public function rendering(View $view): void
    {
        $view->title(__('messages.professionals'));
    }

    /** @return LengthAwarePaginator<int, Professional> */
    #[Computed]
    public function professionals(): LengthAwarePaginator
    {
        $search = $this->searchTerm();
        $status = $this->appliedFilter('status');
        $serviceId = $this->appliedFilter('service');
        $days = $this->appliedFilterValues('days');

        return Professional::with(['user', 'services', 'workingHours'])
            ->withCount('bookings')
            ->when($search, fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($status, fn (Builder $query) => $query->where('is_active', $status === 'active'))
            ->when($serviceId, fn (Builder $query) => $query->whereHas('services', fn (Builder $services) => $services->whereKey((int) $serviceId)))
            ->when($days !== [], fn (Builder $query) => $query->whereHas('workingHours', fn (Builder $hours) => $hours->whereIn('day_of_week', array_map(intval(...), $days))))
            ->orderByName()
            ->paginate($this->perPage);
    }

    /** @return Collection<int, Service> */
    #[Computed]
    public function serviceOptions(): Collection
    {
        return Service::orderBy('name')->get();
    }

    /** @return array<string, string> */
    #[Computed]
    public function dayOptions(): array
    {
        $names = WorkingHour::dayNames();

        return collect([1, 2, 3, 4, 5, 6, 0])->mapWithKeys(fn (int $day) => [(string) $day => $names[$day]])->all();
    }

    /** @return array<string, array{label: string, options: array<string, string>, multiple?: bool}> */
    protected function filterDefinitions(): array
    {
        return [
            'status' => [
                'label' => __('messages.status'),
                'options' => ['active' => __('messages.active'), 'inactive' => __('messages.inactive')],
            ],
            'service' => [
                'label' => __('messages.service'),
                'options' => $this->serviceOptions->mapWithKeys(fn (Service $service) => [(string) $service->id => $service->name])->all(),
            ],
            'days' => [
                'label' => __('messages.days'),
                'options' => $this->dayOptions,
                'multiple' => true,
            ],
        ];
    }

    #[Computed]
    public function canCreateProfessional(): bool
    {
        return (bool) auth()->user()?->can('professionals.create')
            && (bool) app(CurrentTenant::class)->get()?->enablesRole(UserRole::Professional);
    }

    #[On('professional-saved')]
    public function refreshProfessionals(): void
    {
        unset($this->professionals);
    }

    #[Computed]
    public function statusChangingProfessional(): ?Professional
    {
        return $this->statusChangingId ? Professional::with('user')->find($this->statusChangingId) : null;
    }

    public function confirmStatusChange(int $professionalId): void
    {
        $this->authorize('professionals.toggle-status');
        $this->statusChangingId = Professional::findOrFail($professionalId)->id;
        unset($this->statusChangingProfessional);

        Flux::modal('professional-status')->show();
    }

    public function changeStatus(UpdateProfessionalStatus $updateProfessionalStatus): void
    {
        $this->authorize('professionals.toggle-status');

        $professional = Professional::findOrFail($this->statusChangingId);
        $updateProfessionalStatus->handle($professional, ! $professional->is_active);

        Flux::modal('professional-status')->close();
        Flux::toast($professional->is_active ? __('messages.professional_activated') : __('messages.professional_deactivated'), variant: 'success');

        $this->statusChangingId = null;
        unset($this->professionals, $this->statusChangingProfessional);
    }

    public function confirmDelete(int $professionalId): void
    {
        $this->authorize('professionals.destroy');
        $this->deletingId = $professionalId;

        Flux::modal('professional-delete')->show();
    }

    public function delete(DeleteProfessional $deleteProfessional): void
    {
        $this->authorize('professionals.destroy');

        try {
            $deleteProfessional->handle(Professional::findOrFail($this->deletingId));
            Flux::toast(__('messages.professional_deleted'), variant: 'success');
        } catch (CannotDeleteRecordException $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        }

        Flux::modal('professional-delete')->close();
        $this->deletingId = null;
        unset($this->professionals);
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('messages.professionals')" :subtitle="__('messages.team_services_and_working_days')">
        @if ($this->canCreateProfessional)
            <x-slot:actions>
                <flux:button variant="primary" icon="plus" x-on:click="$dispatch('open-professional-form')">{{ __('messages.new_professional') }}</flux:button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <x-data-table :search-placeholder="__('messages.search_professionals')" :active-filters="$this->activeFilters">
        <x-slot:filters>
            <flux:select wire:model="filters.status" :label="__('messages.status')">
                <flux:select.option value="">{{ __('messages.all') }}</flux:select.option>
                <flux:select.option value="active">{{ __('messages.active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('messages.inactive') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model="filters.service" :label="__('messages.service')">
                <flux:select.option value="">{{ __('messages.all') }}</flux:select.option>
                @foreach ($this->serviceOptions as $service)
                    <flux:select.option :value="(string) $service->id" wire:key="filter-service-{{ $service->id }}">{{ $service->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="filters.days" variant="listbox" multiple :label="__('messages.days')" :placeholder="__('messages.all')">
                @foreach ($this->dayOptions as $day => $dayName)
                    <flux:select.option :value="(string) $day" wire:key="filter-day-{{ $day }}">{{ $dayName }}</flux:select.option>
                @endforeach
            </flux:select>
        </x-slot:filters>

        @if ($this->professionals->isEmpty())
            <x-data-table.empty :heading="__('messages.no_professionals_found')" :description="__('messages.add_professional_to_open_bookings')" icon="users" :filtering="$this->activeFilters !== []" />
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('messages.professional') }}</flux:table.column>
                    <flux:table.column>{{ __('messages.services') }}</flux:table.column>
                    <flux:table.column>{{ __('messages.days') }}</flux:table.column>
                    <flux:table.column>{{ __('messages.status') }}</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">{{ __('messages.actions') }}</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->professionals as $professional)
                        <flux:table.row :key="$professional->id">
                            <flux:table.cell class="py-3">
                                <div class="flex items-center gap-3">
                                    <flux:avatar size="sm" circle :src="$professional->photo_url" :name="$professional->name" />
                                    <div class="flex min-w-0 flex-col">
                                        <flux:text variant="strong" class="font-medium">{{ $professional->name }}</flux:text>
                                        <flux:text size="sm">{{ $professional->user->email }}</flux:text>
                                    </div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex max-w-xs flex-wrap gap-1">
                                    @forelse ($professional->services as $service)
                                        <flux:badge size="sm">{{ $service->name }}</flux:badge>
                                    @empty
                                        <flux:text>{{ __('messages.none') }}</flux:text>
                                    @endforelse
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $professional->workingDaysSummary() }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($professional->is_active)
                                    <flux:badge color="green" size="sm" inset="top bottom">{{ __('messages.active') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm" inset="top bottom">{{ __('messages.inactive') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @canany(['professionals.update', 'professionals.toggle-status', 'professionals.destroy'])
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('messages.actions').': '.$professional->name" />

                                        <flux:menu>
                                            @can('professionals.update')
                                                <flux:menu.item icon="pencil-square" x-on:click="$dispatch('open-professional-form', { professionalId: {{ $professional->id }} })">{{ __('messages.edit') }}</flux:menu.item>
                                            @endcan
                                            @can('professionals.toggle-status')
                                                @if ($professional->is_active)
                                                    <flux:menu.item icon="pause-circle" wire:click="confirmStatusChange({{ $professional->id }})">{{ __('messages.deactivate') }}</flux:menu.item>
                                                @else
                                                    <flux:menu.item icon="play-circle" wire:click="confirmStatusChange({{ $professional->id }})">{{ __('messages.activate') }}</flux:menu.item>
                                                @endif
                                            @endcan
                                            @if ($professional->bookings_count === 0)
                                                @can('professionals.destroy')
                                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $professional->id }})">{{ __('messages.delete') }}</flux:menu.item>
                                                @endcan
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                @endcanany
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <x-slot:footer>
                <x-data-table.pagination
                    :from="$this->professionals->firstItem()"
                    :to="$this->professionals->lastItem()"
                    :total="$this->professionals->total()"
                    :current-page="$this->professionals->currentPage()"
                    :last-page="$this->professionals->lastPage()"
                />
            </x-slot:footer>
        @endif
    </x-data-table>

    <flux:modal name="professional-status" class="w-full p-0! md:w-md" :closable="false">
        <div class="flex items-center gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <flux:avatar :icon="$this->statusChangingProfessional?->is_active ? 'pause-circle' : 'play-circle'" class="shrink-0 bg-table-header text-table-header-content" />

            <div class="min-w-0 flex-1">
                <flux:heading size="lg">{{ $this->statusChangingProfessional?->is_active ? __('messages.deactivate_professional') : __('messages.activate_professional') }}</flux:heading>
                <flux:text>{{ $this->statusChangingProfessional?->is_active ? __('messages.inactive_professionals_hidden') : __('messages.professional_offered_again') }}</flux:text>
            </div>

            <flux:modal.close>
                <flux:button variant="ghost" size="sm" icon="x-mark" :aria-label="__('messages.close')" />
            </flux:modal.close>
        </div>

        <div class="flex items-center justify-end gap-2 px-6 py-4">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('messages.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="primary" wire:click="changeStatus" wire:loading.attr="disabled" wire:target="changeStatus">{{ $this->statusChangingProfessional?->is_active ? __('messages.deactivate') : __('messages.activate') }}</flux:button>
        </div>
    </flux:modal>

    <livewire:professional-form />

    <flux:modal name="professional-delete" class="w-full p-0! md:w-md" :closable="false">
        <div class="flex items-center gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <flux:avatar icon="trash" color="red" class="shrink-0" />

            <div class="min-w-0 flex-1">
                <flux:heading size="lg">{{ __('messages.delete_professional') }}</flux:heading>
                <flux:text>{{ __('messages.professional_permanently_removed') }}</flux:text>
            </div>

            <flux:modal.close>
                <flux:button variant="ghost" size="sm" icon="x-mark" :aria-label="__('messages.close')" />
            </flux:modal.close>
        </div>

        <div class="flex items-center justify-end gap-2 px-6 py-4">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('messages.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">{{ __('messages.delete') }}</flux:button>
        </div>
    </flux:modal>
</div>
