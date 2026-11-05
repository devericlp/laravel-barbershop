<?php

use App\Actions\Services\CreateService;
use App\Actions\Services\DeleteService;
use App\Actions\Services\UpdateService;
use App\Actions\Services\UpdateServiceStatus;
use App\Data\ServiceData;
use App\Exceptions\CannotDeleteRecordException;
use App\Livewire\DataTableComponent;
use App\Models\Service;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends DataTableComponent
{
    use WithFileUploads;

    #[Locked]
    public ?int $editingId = null;

    public ?int $deletingId = null;

    #[Locked]
    public ?int $statusChangingId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:500')]
    public string $description = '';

    #[Validate('nullable|image|mimes:jpg,jpeg,png,webp|max:2048')]
    public ?TemporaryUploadedFile $image = null;

    public bool $removeImage = false;

    #[Validate('required|regex:/^\d{1,3}(\.\d{3})*(,\d{1,2})?$/')]
    public string $price = '';

    #[Validate('required|integer|min:5|max:480')]
    public ?int $durationMinutes = null;


    public function rendering(View $view): void
    {
        $view->title(__('messages.services'));
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('messages.name'),
            'description' => __('messages.description'),
            'image' => __('messages.image'),
            'price' => __('messages.price'),
            'durationMinutes' => __('messages.duration'),
        ];
    }

    #[Computed]
    public function imagePreviewUrl(): ?string
    {
        if ($this->image?->isPreviewable()) {
            return $this->image->temporaryUrl();
        }

        if ($this->removeImage || $this->editingId === null) {
            return null;
        }

        return Service::findOrFail($this->editingId)->image_url;
    }

    public function updatedImage(): void
    {
        $this->validateOnly('image');
        $this->removeImage = false;
    }

    public function clearImage(): void
    {
        $this->image = null;
        $this->removeImage = true;
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'price.regex' => __('messages.enter_price_format'),
        ];
    }

    /** @return LengthAwarePaginator<int, Service> */
    #[Computed]
    public function services(): LengthAwarePaginator
    {
        $search = $this->searchTerm();
        $status = $this->appliedFilter('status');
        $duration = $this->appliedFilter('duration');

        return Service::withCount('bookings')
            ->when($search, fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->when($status, fn (Builder $query) => $query->where('is_active', $status === 'active'))
            ->when($duration === 'short', fn (Builder $query) => $query->where('duration_minutes', '<=', 30))
            ->when($duration === 'medium', fn (Builder $query) => $query->whereBetween('duration_minutes', [31, 60]))
            ->when($duration === 'long', fn (Builder $query) => $query->where('duration_minutes', '>', 60))
            ->orderBy('name')
            ->paginate($this->perPage);
    }

    /** @return array<string, array{label: string, options: array<string, string>}> */
    protected function filterDefinitions(): array
    {
        return [
            'status' => [
                'label' => __('messages.status'),
                'options' => ['active' => __('messages.active'), 'inactive' => __('messages.inactive')],
            ],
            'duration' => [
                'label' => __('messages.duration'),
                'options' => [
                    'short' => __('messages.up_to_30_minutes'),
                    'medium' => __('messages.from_31_to_60_minutes'),
                    'long' => __('messages.over_60_minutes'),
                ],
            ],
        ];
    }

    public function create(): void
    {
        $this->authorize('services.create');
        $this->resetForm();

        Flux::modal('service-form')->show();
    }

    public function edit(int $serviceId): void
    {
        $this->authorize('services.update');

        $service = Service::findOrFail($serviceId);

        $this->resetForm();
        $this->editingId = $service->id;
        $this->name = $service->name;
        $this->description = $service->description ?? '';
        $this->price = number_format($service->price_cents / 100, 2, ',', '.');
        $this->durationMinutes = $service->duration_minutes;

        Flux::modal('service-form')->show();
    }

    public function save(CreateService $createService, UpdateService $updateService): void
    {
        $this->authorize($this->editingId ? 'services.update' : 'services.create');
        $this->validate();

        $priceCents = $this->priceInCents();

        if ($priceCents <= 0) {
            $this->addError('price', __('messages.price_greater_than_zero'));

            return;
        }

        $data = new ServiceData(
            name: trim($this->name),
            priceCents: $priceCents,
            durationMinutes: (int) $this->durationMinutes,
            description: trim($this->description) ?: null,
            image: $this->image,
            removeImage: $this->removeImage,
        );

        if ($this->editingId) {
            $updateService->handle(Service::findOrFail($this->editingId), $data);
        } else {
            $createService->handle($data);
        }

        Flux::modal('service-form')->close();
        Flux::toast($this->editingId ? __('messages.service_updated') : __('messages.service_created'), variant: 'success');

        $this->resetForm();
        unset($this->services);
    }

    #[Computed]
    public function statusChangingService(): ?Service
    {
        return $this->statusChangingId ? Service::find($this->statusChangingId) : null;
    }

    public function confirmStatusChange(int $serviceId): void
    {
        $this->authorize('services.toggle-status');
        $this->statusChangingId = Service::findOrFail($serviceId)->id;
        unset($this->statusChangingService);

        Flux::modal('service-status')->show();
    }

    public function changeStatus(UpdateServiceStatus $updateServiceStatus): void
    {
        $this->authorize('services.toggle-status');

        $service = Service::findOrFail($this->statusChangingId);
        $updateServiceStatus->handle($service, ! $service->is_active);

        Flux::modal('service-status')->close();
        Flux::toast($service->is_active ? __('messages.service_activated') : __('messages.service_deactivated'), variant: 'success');

        $this->statusChangingId = null;
        unset($this->services, $this->statusChangingService);
    }

    public function confirmDelete(int $serviceId): void
    {
        $this->authorize('services.destroy');
        $this->deletingId = $serviceId;

        Flux::modal('service-delete')->show();
    }

    public function delete(DeleteService $deleteService): void
    {
        $this->authorize('services.destroy');

        try {
            $deleteService->handle(Service::findOrFail($this->deletingId));
            Flux::toast(__('messages.service_deleted'), variant: 'success');
        } catch (CannotDeleteRecordException $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        }

        Flux::modal('service-delete')->close();
        $this->deletingId = null;
        unset($this->services);
    }

    private function priceInCents(): int
    {
        return (int) round((float) str_replace(['.', ','], ['', '.'], $this->price) * 100);
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'description', 'image', 'removeImage', 'price', 'durationMinutes');
        $this->resetValidation();
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('messages.services')" :subtitle="__('messages.services_offered_with_price_and_duration')">
        @can('services.create')
            <x-slot:actions>
                <flux:button variant="primary" icon="plus" wire:click="create">{{ __('messages.new_service') }}</flux:button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-data-table :search-placeholder="__('messages.search_services')" :active-filters="$this->activeFilters">
        <x-slot:filters>
            <flux:select wire:model="filters.status" :label="__('messages.status')">
                <flux:select.option value="">{{ __('messages.all') }}</flux:select.option>
                <flux:select.option value="active">{{ __('messages.active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('messages.inactive') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model="filters.duration" :label="__('messages.duration')">
                <flux:select.option value="">{{ __('messages.all') }}</flux:select.option>
                <flux:select.option value="short">{{ __('messages.up_to_30_minutes') }}</flux:select.option>
                <flux:select.option value="medium">{{ __('messages.from_31_to_60_minutes') }}</flux:select.option>
                <flux:select.option value="long">{{ __('messages.over_60_minutes') }}</flux:select.option>
            </flux:select>
        </x-slot:filters>

        @if ($this->services->isEmpty())
            <x-data-table.empty :heading="__('messages.no_services_found')" :description="__('messages.add_first_service')" icon="scissors" :filtering="$this->activeFilters !== []" />
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('messages.services') }}</flux:table.column>
                    <flux:table.column>{{ __('messages.price') }}</flux:table.column>
                    <flux:table.column>{{ __('messages.duration') }}</flux:table.column>
                    <flux:table.column>{{ __('messages.status') }}</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">{{ __('messages.actions') }}</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->services as $service)
                        <flux:table.row :key="$service->id">
                            <flux:table.cell class="py-3">
                                <div class="flex items-center gap-3">
                                    <flux:avatar size="lg" :src="$service->image_url" icon="scissors" :alt="$service->name" class="bg-table-header text-table-header-content" />
                                    <div class="flex min-w-0 flex-col">
                                        <flux:text variant="strong" class="font-medium">{{ $service->name }}</flux:text>
                                        @if ($service->description)
                                            <flux:text size="sm" class="max-w-xs truncate" :title="$service->description">{{ $service->description }}</flux:text>
                                        @endif
                                    </div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="tabular-nums">{{ $service->formatted_price }}</flux:table.cell>
                            <flux:table.cell>{{ $service->durationLabel() }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($service->is_active)
                                    <flux:badge color="green" size="sm" inset="top bottom">{{ __('messages.active') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm" inset="top bottom">{{ __('messages.inactive') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @canany(['services.update', 'services.toggle-status', 'services.destroy'])
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('messages.actions').': '.$service->name" />

                                        <flux:menu>
                                            @can('services.update')
                                                <flux:menu.item icon="pencil-square" wire:click="edit({{ $service->id }})">{{ __('messages.edit') }}</flux:menu.item>
                                            @endcan
                                            @can('services.toggle-status')
                                                @if ($service->is_active)
                                                    <flux:menu.item icon="pause-circle" wire:click="confirmStatusChange({{ $service->id }})">{{ __('messages.deactivate') }}</flux:menu.item>
                                                @else
                                                    <flux:menu.item icon="play-circle" wire:click="confirmStatusChange({{ $service->id }})">{{ __('messages.activate') }}</flux:menu.item>
                                                @endif
                                            @endcan
                                            @if ($service->bookings_count === 0)
                                                @can('services.destroy')
                                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $service->id }})">{{ __('messages.delete') }}</flux:menu.item>
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
                    :from="$this->services->firstItem()"
                    :to="$this->services->lastItem()"
                    :total="$this->services->total()"
                    :current-page="$this->services->currentPage()"
                    :last-page="$this->services->lastPage()"
                />
            </x-slot:footer>
        @endif
    </x-data-table>

    <flux:modal name="service-form" class="w-full p-0! md:w-3xl md:max-w-3xl" :closable="false">
        <form wire:submit="save" class="flex max-h-[calc(100dvh-4rem)] flex-col">
            <div class="flex items-center gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:avatar icon="scissors" class="shrink-0 bg-table-header text-table-header-content" />

                <div class="min-w-0 flex-1">
                    <flux:heading size="lg">{{ $editingId ? __('messages.edit_service') : __('messages.new_service') }}</flux:heading>
                    <flux:text>{{ $editingId ? __('messages.update_service_shown_in_bookings') : __('messages.register_service_to_offer_in_bookings') }}</flux:text>
                </div>

                <flux:modal.close>
                    <flux:button variant="ghost" size="sm" icon="x-mark" :aria-label="__('messages.close')" />
                </flux:modal.close>
            </div>

            <div class="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto p-6">
                <div class="grid gap-6 md:grid-cols-5">
                    <div class="flex flex-col gap-4 md:col-span-3">
                        <flux:input wire:model="name" :label="__('messages.name')" :placeholder="__('messages.haircut_and_beard')" required />

                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model="price" :label="__('messages.price_brl')" mask:dynamic="$money($input, ',')" placeholder="45,00" inputmode="decimal" required />

                            <flux:input wire:model="durationMinutes" :label="__('messages.duration_minutes')" type="number" min="5" max="480" step="5" placeholder="30" required />
                        </div>

                        <flux:textarea wire:model="description" :label="__('messages.description')" :badge="__('messages.optional')" :placeholder="__('messages.service_description_placeholder')" rows="3" maxlength="500" />
                    </div>

                    <div class="flex flex-col gap-2 md:col-span-2">
                        <flux:file-upload wire:model="image" accept="image/jpeg,image/png,image/webp" :label="__('messages.image')" :badge="__('messages.optional')">
                            <div class="flex cursor-pointer flex-col items-center gap-2 rounded-lg md:gap-3 border border-dashed border-zinc-300 p-4 text-center transition-colors hover:bg-zinc-50 in-data-dragging:border-emerald-500 in-data-dragging:bg-emerald-50 dark:border-white/20 dark:hover:bg-white/5 dark:in-data-dragging:bg-white/10">
                                <div class="relative">
                                    <flux:avatar :src="$this->imagePreviewUrl" icon="scissors" class="size-16! bg-table-header text-table-header-content md:size-24!" />

                                    <div
                                        wire:loading.class.remove="opacity-0"
                                        wire:target="image"
                                        class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-lg bg-white/75 opacity-0 transition-opacity in-data-loading:opacity-100 dark:bg-zinc-900/75"
                                        aria-hidden="true"
                                    >
                                        <flux:icon.loading class="text-table-header-content" />
                                    </div>
                                </div>

                                <flux:button as="div" size="sm" icon="photo" class="in-data-loading:pointer-events-none in-data-loading:opacity-50">{{ __('messages.choose_image') }}</flux:button>

                                <div>
                                    <flux:text size="sm">{{ __('messages.or_drag_and_drop_here') }}</flux:text>
                                    <flux:text size="sm">{{ __('messages.jpg_png_webp_up_to_2mb') }}</flux:text>
                                </div>
                            </div>
                        </flux:file-upload>

                        @if ($this->imagePreviewUrl)
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="clearImage" class="self-center">{{ __('messages.remove_image') }}</flux:button>
                        @else
                            <flux:text size="sm" class="text-center">{{ __('messages.no_image_list_shows_scissors_icon') }}</flux:text>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('messages.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save,image">{{ __('messages.save_service') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="service-status" class="w-full p-0! md:w-md" :closable="false">
        <div class="flex items-center gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <flux:avatar :icon="$this->statusChangingService?->is_active ? 'pause-circle' : 'play-circle'" class="shrink-0 bg-table-header text-table-header-content" />

            <div class="min-w-0 flex-1">
                <flux:heading size="lg">{{ $this->statusChangingService?->is_active ? __('messages.deactivate_service') : __('messages.activate_service') }}</flux:heading>
                <flux:text>{{ $this->statusChangingService?->is_active ? __('messages.inactive_services_hidden') : __('messages.service_offered_again') }}</flux:text>
            </div>

            <flux:modal.close>
                <flux:button variant="ghost" size="sm" icon="x-mark" :aria-label="__('messages.close')" />
            </flux:modal.close>
        </div>

        <div class="flex items-center justify-end gap-2 px-6 py-4">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('messages.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="primary" wire:click="changeStatus" wire:loading.attr="disabled" wire:target="changeStatus">{{ $this->statusChangingService?->is_active ? __('messages.deactivate') : __('messages.activate') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="service-delete" class="w-full p-0! md:w-md" :closable="false">
        <div class="flex items-center gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <flux:avatar icon="trash" color="red" class="shrink-0" />

            <div class="min-w-0 flex-1">
                <flux:heading size="lg">{{ __('messages.delete_service') }}</flux:heading>
                <flux:text>{{ __('messages.service_permanently_removed') }}</flux:text>
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
