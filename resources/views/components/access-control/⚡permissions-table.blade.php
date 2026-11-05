<?php

use App\Actions\Permissions\CreatePermission;
use App\Actions\Permissions\DeletePermission;
use App\Actions\Permissions\UpdatePermission;
use App\Exceptions\CannotDeleteRecordException;
use App\Livewire\DataTableComponent;
use App\Models\Permission;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

new class extends DataTableComponent
{
    #[Locked]
    public ?int $editingPermissionId = null;

    public string $permissionName = '';

    #[Locked]
    public ?int $deletingPermissionId = null;

    /** @return LengthAwarePaginator<int, Permission> */
    #[Computed]
    public function permissions(): LengthAwarePaginator
    {
        $search = $this->searchTerm();
        $type = $this->appliedFilter('type');

        return Permission::with('roles')
            ->when($search, fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->when($type, fn (Builder $query) => $query->where('is_system', $type === 'system'))
            ->orderBy('name')
            ->paginate($this->perPage);
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

    #[On('create-permission')]
    public function createPermission(): void
    {
        $this->reset('editingPermissionId', 'permissionName');
        $this->resetValidation();

        Flux::modal('permission-form')->show();
    }

    public function editPermission(int $permissionId): void
    {
        $permission = Permission::findOrFail($permissionId);

        $this->resetValidation();
        $this->editingPermissionId = $permission->id;
        $this->permissionName = $permission->name;

        Flux::modal('permission-form')->show();
    }

    public function savePermission(CreatePermission $createPermission, UpdatePermission $updatePermission): void
    {
        $this->permissionName = trim($this->permissionName);

        $this->validate([
            'permissionName' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+\.[a-z0-9-]+$/', Rule::unique('permissions', 'name')->ignore($this->editingPermissionId)],
        ], [
            'permissionName.regex' => __('messages.permission_name_format'),
        ], [
            'permissionName' => __('messages.name'),
        ]);

        if ($this->editingPermissionId) {
            $updatePermission->handle(Permission::findOrFail($this->editingPermissionId), $this->permissionName);
            Flux::toast(__('messages.permission_updated'), variant: 'success');
        } else {
            $createPermission->handle($this->permissionName);
            Flux::toast(__('messages.permission_created'), variant: 'success');
        }

        Flux::modal('permission-form')->close();
        unset($this->permissions);
    }

    public function confirmDeletePermission(int $permissionId): void
    {
        $this->deletingPermissionId = Permission::findOrFail($permissionId)->id;

        Flux::modal('permission-delete')->show();
    }

    public function deletePermission(DeletePermission $deletePermission): void
    {
        try {
            $deletePermission->handle(Permission::findOrFail($this->deletingPermissionId));
            Flux::toast(__('messages.permission_deleted'), variant: 'success');
        } catch (CannotDeleteRecordException $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        }

        Flux::modal('permission-delete')->close();
        $this->deletingPermissionId = null;
        unset($this->permissions);
    }
};
?>

<div>
    <x-data-table :search-placeholder="__('messages.search_permissions')" :active-filters="$this->activeFilters">
        <x-slot:filters>
            <flux:select wire:model="filters.type" :label="__('messages.type')">
                <flux:select.option value="">{{ __('messages.all') }}</flux:select.option>
                <flux:select.option value="system">{{ __('messages.system') }}</flux:select.option>
                <flux:select.option value="custom">{{ __('messages.custom') }}</flux:select.option>
            </flux:select>
        </x-slot:filters>

        @if ($this->permissions->isEmpty())
            <x-data-table.empty :heading="__('messages.no_permissions_found')" :description="__('messages.use_new_permission_to_create')" icon="key" :filtering="$this->activeFilters !== []" />
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('messages.permission') }}</flux:table.column>
                    <flux:table.column>{{ __('messages.roles') }}</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">{{ __('messages.actions') }}</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->permissions as $permission)
                        <flux:table.row :key="'permission-'.$permission->id">
                            <flux:table.cell variant="strong" class="py-4">
                                <div class="flex items-center gap-2">
                                    <code class="text-sm">{{ $permission->name }}</code>
                                    @if ($permission->is_system)
                                        <flux:badge size="sm" color="zinc" inset="top bottom">{{ __('messages.system') }}</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($permission->roles as $holder)
                                        <flux:badge size="sm" inset="top bottom">{{ $holder->label() }}</flux:badge>
                                    @empty
                                        <flux:text size="sm">—</flux:text>
                                    @endforelse
                                </div>
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @unless ($permission->is_system)
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('messages.actions').': '.$permission->name" />

                                        <flux:menu>
                                            <flux:menu.item icon="pencil-square" wire:click="editPermission({{ $permission->id }})">{{ __('messages.edit') }}</flux:menu.item>
                                            <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeletePermission({{ $permission->id }})">{{ __('messages.delete') }}</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                @endunless
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <x-slot:footer>
                <x-data-table.pagination
                    :from="$this->permissions->firstItem()"
                    :to="$this->permissions->lastItem()"
                    :total="$this->permissions->total()"
                    :current-page="$this->permissions->currentPage()"
                    :last-page="$this->permissions->lastPage()"
                />
            </x-slot:footer>
        @endif
    </x-data-table>

    <flux:modal name="permission-form" class="w-full p-0! md:w-lg" :closable="false">
        <form wire:submit="savePermission" class="flex max-h-[calc(100dvh-4rem)] flex-col">
            <div class="flex items-center gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:avatar icon="key" class="shrink-0 bg-table-header text-table-header-content" />

                <div class="min-w-0 flex-1">
                    <flux:heading size="lg">{{ $editingPermissionId ? __('messages.edit_permission') : __('messages.new_permission') }}</flux:heading>
                    <flux:text>{{ __('messages.create_permission_to_assign_roles') }}</flux:text>
                </div>

                <flux:modal.close>
                    <flux:button variant="ghost" size="sm" icon="x-mark" :aria-label="__('messages.close')" />
                </flux:modal.close>
            </div>

            <div class="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto p-6">
                <flux:input wire:model="permissionName" :label="__('messages.name')" :description="__('messages.permission_name_format')" placeholder="reports.index" required />
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('messages.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="savePermission">{{ __('messages.save_permission') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="permission-delete" class="w-full p-0! md:w-md" :closable="false">
        <div class="flex items-center gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <flux:avatar icon="trash" color="red" class="shrink-0" />

            <div class="min-w-0 flex-1">
                <flux:heading size="lg">{{ __('messages.delete_permission') }}</flux:heading>
                <flux:text>{{ __('messages.permission_permanently_removed') }}</flux:text>
            </div>

            <flux:modal.close>
                <flux:button variant="ghost" size="sm" icon="x-mark" :aria-label="__('messages.close')" />
            </flux:modal.close>
        </div>

        <div class="flex items-center justify-end gap-2 px-6 py-4">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('messages.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" wire:click="deletePermission" wire:loading.attr="disabled" wire:target="deletePermission">{{ __('messages.delete') }}</flux:button>
        </div>
    </flux:modal>
</div>
