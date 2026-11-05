<?php

use App\Actions\Tenants\CreateTenant;
use App\Actions\Tenants\UpdateTenant;
use App\Enums\UserRole;
use App\Livewire\DataTableComponent;
use App\Models\Role;
use App\Models\Tenant;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

new class extends DataTableComponent
{
    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public bool $isActive = true;

    public string $managerName = '';

    public string $managerEmail = '';

    public string $managerPassword = '';

    /** @var list<string> */
    public array $roleIds = [];

    public function rendering(View $view): void
    {
        $view->title(__('messages.barbershops'));
    }

    /** @return LengthAwarePaginator<int, Tenant> */
    #[Computed]
    public function tenants(): LengthAwarePaginator
    {
        $search = $this->searchTerm();
        $status = $this->appliedFilter('status');
        $roleId = $this->appliedFilter('role');
        $bookings = $this->appliedFilter('bookings');

        return Tenant::withCount(['users', 'bookings'])
            ->when($search, fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->when($status, fn (Builder $query) => $query->where('is_active', $status === 'active'))
            ->when($roleId, fn (Builder $query) => $query->whereHas('roles', fn (Builder $roles) => $roles->whereKey((int) $roleId)))
            ->when($bookings === 'with', fn (Builder $query) => $query->has('bookings'))
            ->when($bookings === 'without', fn (Builder $query) => $query->doesntHave('bookings'))
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
            'role' => [
                'label' => __('messages.enabled_role'),
                'options' => $this->roleOptions->mapWithKeys(fn (Role $role) => [(string) $role->id => $role->label()])->all(),
            ],
            'bookings' => [
                'label' => __('messages.bookings'),
                'options' => ['with' => __('messages.with_bookings'), 'without' => __('messages.without_bookings')],
            ],
        ];
    }

    /** @return Collection<int, Role> */
    #[Computed]
    public function roleOptions(): Collection
    {
        return Role::assignable()->orderByDesc('is_system')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->reset();
        $this->resetValidation();
        $this->roleIds = $this->roleOptions->where('is_system', true)->pluck('id')->map(fn (int $id) => (string) $id)->values()->all();

        Flux::modal('tenant-form')->show();
    }

    public function edit(int $tenantId): void
    {
        $tenant = Tenant::with('roles')->findOrFail($tenantId);

        $this->reset();
        $this->resetValidation();
        $this->editingId = $tenant->id;
        $this->name = $tenant->name;
        $this->isActive = $tenant->is_active;
        $this->roleIds = $tenant->roles->pluck('id')->map(fn (int $id) => (string) $id)->all();

        Flux::modal('tenant-form')->show();
    }

    public function save(CreateTenant $createTenant, UpdateTenant $updateTenant): void
    {
        $this->validate($this->validationRules(), attributes: [
            'name' => __('messages.name'),
            'managerName' => __('messages.manager_name'),
            'managerEmail' => __('messages.manager_email'),
            'managerPassword' => __('messages.manager_password'),
            'roleIds' => __('messages.roles'),
        ]);

        $roleIds = array_map(intval(...), $this->roleIds);

        if ($this->editingId) {
            $updateTenant->handle(Tenant::findOrFail($this->editingId), trim($this->name), $this->isActive, $roleIds);
            Flux::toast(__('messages.barbershop_updated'), variant: 'success');
        } else {
            $createTenant->handle(trim($this->name), trim($this->managerName), $this->managerEmail, $this->managerPassword, $roleIds);
            Flux::toast(__('messages.barbershop_created_with_manager'), variant: 'success');
        }

        Flux::modal('tenant-form')->close();
        unset($this->tenants);
    }

    /** @return array<string, mixed> */
    private function validationRules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'isActive' => ['boolean'],
            'roleIds' => ['array'],
            'roleIds.*' => ['integer', Rule::exists('roles', 'id')->whereNot('name', UserRole::Admin->value)],
        ];

        if ($this->editingId) {
            return $rules;
        }

        return [
            ...$rules,
            'managerName' => ['required', 'string', 'min:2', 'max:255'],
            'managerEmail' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'managerPassword' => ['required', 'string', Password::defaults()],
            'roleIds' => [...$rules['roleIds'], function (string $attribute, array $value, Closure $fail) {
                if (! in_array((string) $this->roleOptions->firstWhere('name', UserRole::Manager->value)?->id, $value, true)) {
                    $fail(__('messages.manager_role_required'));
                }
            }],
        ];
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('messages.barbershops')" :subtitle="__('messages.barbershops_served_by_platform')">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('messages.new_barbershop') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <x-data-table :search-placeholder="__('messages.search_barbershops')" :active-filters="$this->activeFilters">

        <x-slot:filters>
            <flux:select wire:model="filters.status" :label="__('messages.status')">
                <flux:select.option value="">{{ __('messages.all') }}</flux:select.option>
                <flux:select.option value="active">{{ __('messages.active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('messages.inactive') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model="filters.role" :label="__('messages.enabled_role')">
                <flux:select.option value="">{{ __('messages.all') }}</flux:select.option>
                @foreach ($this->roleOptions as $role)
                    <flux:select.option :value="(string) $role->id" wire:key="filter-role-{{ $role->id }}">{{ $role->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="filters.bookings" :label="__('messages.bookings')">
                <flux:select.option value="">{{ __('messages.all') }}</flux:select.option>
                <flux:select.option value="with">{{ __('messages.with_bookings') }}</flux:select.option>
                <flux:select.option value="without">{{ __('messages.without_bookings') }}</flux:select.option>
            </flux:select>
        </x-slot:filters>

        @if ($this->tenants->isEmpty())
            <x-data-table.empty :heading="__('messages.no_barbershops_found')" icon="building-storefront" :filtering="$this->activeFilters !== []" />
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('messages.barbershop') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('messages.users') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('messages.bookings') }}</flux:table.column>
                    <flux:table.column>{{ __('messages.status') }}</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">{{ __('messages.actions') }}</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->tenants as $tenant)
                        <flux:table.row :key="$tenant->id">
                            <flux:table.cell variant="strong" class="py-4">{{ $tenant->name }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $tenant->users_count }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $tenant->bookings_count }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($tenant->is_active)
                                    <flux:badge color="green" size="sm" inset="top bottom">{{ __('messages.active') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm" inset="top bottom">{{ __('messages.inactive') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('messages.actions').': '.$tenant->name" />

                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="edit({{ $tenant->id }})">{{ __('messages.edit') }}</flux:menu.item>
                                        <flux:menu.item icon="arrow-top-right-on-square" :href="$tenant->bookingUrl()" target="_blank" rel="noopener">{{ __('messages.open_booking_page') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <x-slot:footer>
                <x-data-table.pagination
                    :from="$this->tenants->firstItem()"
                    :to="$this->tenants->lastItem()"
                    :total="$this->tenants->total()"
                    :current-page="$this->tenants->currentPage()"
                    :last-page="$this->tenants->lastPage()"
                />
            </x-slot:footer>
        @endif
    </x-data-table>

    <flux:modal name="tenant-form" class="w-full p-0! md:w-2xl md:max-w-2xl" :closable="false">
        <form wire:submit="save" class="flex max-h-[calc(100dvh-4rem)] flex-col">
            <div class="flex items-center gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:avatar icon="building-storefront" class="shrink-0 bg-table-header text-table-header-content" />

                <div class="min-w-0 flex-1">
                    <flux:heading size="lg">{{ $editingId ? __('messages.edit_barbershop') : __('messages.new_barbershop') }}</flux:heading>
                    <flux:text>{{ $editingId ? __('messages.change_barbershop_name_roles_and_status') : __('messages.create_barbershop_with_first_manager') }}</flux:text>
                </div>

                <flux:modal.close>
                    <flux:button variant="ghost" size="sm" icon="x-mark" :aria-label="__('messages.close')" />
                </flux:modal.close>
            </div>

            <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-6">
                <flux:input wire:model="name" :label="__('messages.barbershop_name')" required />

                <flux:pillbox wire:model="roleIds" multiple :label="__('messages.roles')" :description="__('messages.roles_available_in_barbershop')" :placeholder="__('messages.select_the_roles')">
                    @foreach ($this->roleOptions as $role)
                        <flux:pillbox.option :value="(string) $role->id" wire:key="tenant-role-{{ $role->id }}">{{ $role->label() }}</flux:pillbox.option>
                    @endforeach
                </flux:pillbox>

                @if ($editingId)
                    <flux:switch wire:model="isActive" :label="__('messages.active')" :description="__('messages.inactive_barbershop_cannot_sign_in')" align="left" />
                @else
                    <flux:separator :text="__('messages.first_manager')" />

                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:input wire:model="managerName" :label="__('messages.manager_name')" required />
                        <flux:input wire:model="managerEmail" :label="__('messages.manager_email')" type="email" autocomplete="off" required />
                    </div>

                    <flux:input wire:model="managerPassword" :label="__('messages.manager_password')" :description:trailing="__('messages.initial_sign_in_password')" type="password" autocomplete="new-password" viewable required />
                @endif
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('messages.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('messages.save_barbershop') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
