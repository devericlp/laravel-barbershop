<?php

use App\Actions\Roles\CreateRole;
use App\Actions\Roles\DeleteRole;
use App\Actions\Roles\UpdateRole;
use App\Enums\UserRole;
use App\Exceptions\CannotDeleteRecordException;
use App\Livewire\DataTableComponent;
use App\Models\Permission;
use App\Models\Role;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

new class extends DataTableComponent
{
    #[Locked]
    public ?int $editingRoleId = null;

    public string $roleName = '';

    /** @var list<string> */
    public array $rolePermissionIds = [];

    #[Locked]
    public ?int $deletingRoleId = null;

    /** @return LengthAwarePaginator<int, Role> */
    #[Computed]
    public function roles(): LengthAwarePaginator
    {
        $search = $this->searchTerm();
        $type = $this->appliedFilter('type');

        return Role::assignable()
            ->withCount(['permissions', 'tenants', 'users'])
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhereIn('name', $this->systemRolesLabelled((string) $search))))
            ->when($type, fn (Builder $query) => $query->where('is_system', $type === 'system'))
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->paginate($this->perPage);
    }

    /** @return array<string, Collection<int, Permission>> */
    #[Computed]
    public function permissionsByModule(): array
    {
        return Permission::orderBy('name')->get()->groupBy(fn (Permission $permission) => $permission->module())->all();
    }

    #[Computed]
    public function editingSystemRole(): bool
    {
        return $this->editingRoleId !== null && (bool) Role::find($this->editingRoleId)?->is_system;
    }

    /** @return array<string, array{label: string, options: array<string, string>}> */
    protected function filterDefinitions(): array
    {
        return [
            'type' => [
                'label' => __('messages.type'),
                'options' => ['system' => __('messages.system'), 'custom' => __('messages.custom')],
            ],
        ];
    }

    #[On('create-role')]
    public function createRole(): void
    {
        $this->reset('editingRoleId', 'roleName', 'rolePermissionIds');
        $this->resetValidation();

        Flux::modal('role-form')->show();
    }

    public function editRole(int $roleId): void
    {
        $role = Role::assignable()->with('permissions')->findOrFail($roleId);

        $this->resetValidation();
        $this->editingRoleId = $role->id;
        $this->roleName = $role->name;
        $this->rolePermissionIds = $role->permissions->pluck('id')->map(fn (int $id) => (string) $id)->all();

        Flux::modal('role-form')->show();
    }

    public function saveRole(CreateRole $createRole, UpdateRole $updateRole): void
    {
        $this->roleName = trim($this->roleName);

        $this->validate([
            'roleName' => ['required', 'string', 'min:2', 'max:100', Rule::unique('roles', 'name')->ignore($this->editingRoleId)],
            'rolePermissionIds' => ['array'],
            'rolePermissionIds.*' => ['integer', Rule::exists('permissions', 'id')],
        ], attributes: [
            'roleName' => __('messages.name'),
            'rolePermissionIds' => __('messages.permissions'),
        ]);

        $permissionIds = array_map(intval(...), $this->rolePermissionIds);

        if ($this->editingRoleId) {
            $updateRole->handle(Role::assignable()->findOrFail($this->editingRoleId), $this->roleName, $permissionIds);
            Flux::toast(__('messages.role_updated'), variant: 'success');
        } else {
            $createRole->handle($this->roleName, $permissionIds);
            Flux::toast(__('messages.role_created'), variant: 'success');
        }

        Flux::modal('role-form')->close();
        unset($this->roles);
    }

    public function confirmDeleteRole(int $roleId): void
    {
        $this->deletingRoleId = Role::assignable()->findOrFail($roleId)->id;

        Flux::modal('role-delete')->show();
    }

    public function deleteRole(DeleteRole $deleteRole): void
    {
        try {
            $deleteRole->handle(Role::assignable()->findOrFail($this->deletingRoleId));
            Flux::toast(__('messages.role_deleted'), variant: 'success');
        } catch (CannotDeleteRecordException $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        }

        Flux::modal('role-delete')->close();
        $this->deletingRoleId = null;
        unset($this->roles);
    }

    /** @return list<string> */
    private function systemRolesLabelled(string $search): array
    {
        return collect(UserRole::cases())
            ->filter(fn (UserRole $role) => str_contains(mb_strtolower($role->label()), mb_strtolower($search)))
            ->map(fn (UserRole $role) => $role->value)
            ->values()
            ->all();
    }
};
?>

<div>
    <x-data-table :search-placeholder="__('messages.search_roles')" :active-filters="$this->activeFilters">
        <x-slot:filters>
            <flux:select wire:model="filters.type" :label="__('messages.type')">
                <flux:select.option value="">{{ __('messages.all') }}</flux:select.option>
                <flux:select.option value="system">{{ __('messages.system') }}</flux:select.option>
                <flux:select.option value="custom">{{ __('messages.custom') }}</flux:select.option>
            </flux:select>
        </x-slot:filters>

        @if ($this->roles->isEmpty())
            <x-data-table.empty :heading="__('messages.no_roles_found')" :description="__('messages.use_new_role_to_create')" icon="user-group" :filtering="$this->activeFilters !== []" />
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('messages.role') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('messages.permissions') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('messages.users') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('messages.barbershops') }}</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">{{ __('messages.actions') }}</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->roles as $role)
                        <flux:table.row :key="'role-'.$role->id">
                            <flux:table.cell variant="strong" class="py-4">
                                <div class="flex items-center gap-2">
                                    {{ $role->label() }}
                                    @if ($role->is_system)
                                        <flux:badge size="sm" color="zinc" inset="top bottom">{{ __('messages.system') }}</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $role->permissions_count }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $role->users_count }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $role->tenants_count }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('messages.actions').': '.$role->label()" />

                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="editRole({{ $role->id }})">{{ __('messages.edit') }}</flux:menu.item>
                                        @unless ($role->is_system || $role->users_count > 0)
                                            <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeleteRole({{ $role->id }})">{{ __('messages.delete') }}</flux:menu.item>
                                        @endunless
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <x-slot:footer>
                <x-data-table.pagination
                    :from="$this->roles->firstItem()"
                    :to="$this->roles->lastItem()"
                    :total="$this->roles->total()"
                    :current-page="$this->roles->currentPage()"
                    :last-page="$this->roles->lastPage()"
                />
            </x-slot:footer>
        @endif
    </x-data-table>

    <flux:modal name="role-form" class="w-full p-0! md:w-5xl md:max-w-5xl" :closable="false">
        <form wire:submit="saveRole" class="flex max-h-[calc(100dvh-4rem)] flex-col">
            <div class="flex items-center gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:avatar icon="user-group" class="shrink-0 bg-table-header text-table-header-content" />

                <div class="min-w-0 flex-1">
                    <flux:heading size="lg">{{ $editingRoleId ? __('messages.edit_role') : __('messages.new_role') }}</flux:heading>
                    <flux:text>{{ __('messages.set_role_name_and_permissions') }}</flux:text>
                </div>

                <flux:modal.close>
                    <flux:button variant="ghost" size="sm" icon="x-mark" :aria-label="__('messages.close')" />
                </flux:modal.close>
            </div>

            <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-6 py-4">
                <flux:input wire:model="roleName" :label="__('messages.name')" :description="$this->editingSystemRole ? __('messages.system_role_name_fixed') : null" :disabled="$this->editingSystemRole" required />

                <flux:checkbox.group wire:model="rolePermissionIds" :label="__('messages.permissions')">
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($this->permissionsByModule as $module => $modulePermissions)
                            <flux:card size="sm" class="flex flex-col gap-1 p-3!" wire:key="module-{{ $module }}">
                                <flux:heading size="sm"><code>{{ $module }}</code></flux:heading>
                                @foreach ($modulePermissions as $permission)
                                    <flux:checkbox :value="(string) $permission->id" :label="$permission->name" wire:key="permission-option-{{ $permission->id }}" />
                                @endforeach
                            </flux:card>
                        @endforeach
                    </div>
                </flux:checkbox.group>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('messages.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveRole">{{ __('messages.save_role') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="role-delete" class="w-full p-0! md:w-md" :closable="false">
        <div class="flex items-center gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <flux:avatar icon="trash" color="red" class="shrink-0" />

            <div class="min-w-0 flex-1">
                <flux:heading size="lg">{{ __('messages.delete_role') }}</flux:heading>
                <flux:text>{{ __('messages.role_permanently_removed') }}</flux:text>
            </div>

            <flux:modal.close>
                <flux:button variant="ghost" size="sm" icon="x-mark" :aria-label="__('messages.close')" />
            </flux:modal.close>
        </div>

        <div class="flex items-center justify-end gap-2 px-6 py-4">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('messages.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" wire:click="deleteRole" wire:loading.attr="disabled" wire:target="deleteRole">{{ __('messages.delete') }}</flux:button>
        </div>
    </flux:modal>
</div>
