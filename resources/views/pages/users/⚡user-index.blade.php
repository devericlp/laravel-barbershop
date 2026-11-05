<?php

use App\Actions\Customers\DetachUserFromCustomer;
use App\Actions\Customers\LinkUserAsCustomer;
use App\Actions\Users\DeleteUser;
use App\Enums\UserRole;
use App\Exceptions\CannotDeleteRecordException;
use App\Exceptions\CustomerLinkException;
use App\Exceptions\RoleChangeException;
use App\Exceptions\RoleNotEnabledException;
use App\Livewire\DataTableComponent;
use App\Models\User;
use App\Support\CurrentTenant;
use App\Support\PhoneNumber;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

new class extends DataTableComponent
{
    public ?int $deletingId = null;

    #[Locked]
    public ?int $linkingId = null;

    public string $linkPhone = '';

    #[Locked]
    public ?int $unlinkingId = null;

    public function rendering(View $view): void
    {
        $view->title(__('messages.users'));
    }


    /** @return list<UserRole> */
    #[Computed]
    public function roles(): array
    {
        return [UserRole::Manager, UserRole::Professional];
    }

    /** @return list<array{role: string, label: string, icon: string, event: string}> */
    #[Computed]
    public function creatableRoles(): array
    {
        $tenant = app(CurrentTenant::class)->get();
        $user = auth()->user();

        $options = [
            ['role' => UserRole::Professional, 'icon' => 'scissors', 'permission' => 'professionals.create', 'event' => 'open-professional-form'],
            ['role' => UserRole::Manager, 'icon' => 'shield-check', 'permission' => 'users.create', 'event' => 'open-user-form'],
        ];

        $creatable = array_filter($options, fn (array $option) => (bool) $tenant?->enablesRole($option['role'])
            && (bool) $user?->can($option['permission']));

        return array_values(array_map(fn (array $option) => [
            'role' => $option['role']->value,
            'label' => $option['role']->label(),
            'icon' => $option['icon'],
            'event' => $option['event'],
        ], $creatable));
    }

    /** @return LengthAwarePaginator<int, User> */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $search = $this->searchTerm() ?? '';
        $digits = PhoneNumber::normalize($search);
        $role = UserRole::tryFrom((string) $this->appliedFilter('role'));

        return User::with(['customer', 'professional', 'roles'])
            ->when($role, fn (Builder $query) => $query->withRole($role))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->when($digits !== '', fn (Builder $phone) => $phone->orWhereHas('customer', fn (Builder $customer) => $customer->where('phone', 'like', "%{$digits}%")))))
            ->orderBy('name')
            ->paginate($this->perPage);
    }

    /** @return array<string, array{label: string, options: array<string, string>}> */
    protected function filterDefinitions(): array
    {
        return [
            'role' => [
                'label' => __('messages.role'),
                'options' => collect($this->roles)->mapWithKeys(fn (UserRole $role) => [$role->value => $role->label()])->all(),
            ],
        ];
    }

    #[On('user-saved')]
    #[On('professional-saved')]
    public function refreshUsers(): void
    {
        unset($this->users);
    }

    public function confirmLinkCustomer(int $userId): void
    {
        $this->authorize('users.link-customer');
        $this->linkingId = User::findOrFail($userId)->id;
        $this->reset('linkPhone');
        $this->resetValidation();

        Flux::modal('user-link-customer')->show();
    }

    public function linkCustomer(LinkUserAsCustomer $linkUserAsCustomer): void
    {
        $this->authorize('users.link-customer');

        $this->validate([
            'linkPhone' => ['required', 'string', function (string $attribute, string $value, Closure $fail) {
                if (! PhoneNumber::isValidMobile($value)) {
                    $fail(__('messages.enter_brazilian_mobile'));
                }
            }],
        ], attributes: ['linkPhone' => __('messages.mobile')]);

        $user = User::findOrFail($this->linkingId);

        abort_unless($user->canBeLinkedAsCustomer(), 403);

        try {
            $linkUserAsCustomer->handle($user, $this->linkPhone);
        } catch (ValidationException $exception) {
            $this->addError('linkPhone', $exception->validator->errors()->first());

            return;
        } catch (CustomerLinkException|RoleNotEnabledException $exception) {
            $this->addError('linkPhone', $exception->getMessage());

            return;
        }

        Flux::modal('user-link-customer')->close();
        Flux::toast(__('messages.customer_linked'), variant: 'success');
        $this->linkingId = null;
        unset($this->users);
    }

    public function confirmUnlinkCustomer(int $userId): void
    {
        $this->authorize('users.link-customer');
        $this->unlinkingId = User::findOrFail($userId)->id;

        Flux::modal('user-unlink-customer')->show();
    }

    public function unlinkCustomer(DetachUserFromCustomer $detachUserFromCustomer): void
    {
        $this->authorize('users.link-customer');

        $user = User::with('customer')->findOrFail($this->unlinkingId);

        try {
            abort_if($user->customer === null, 404);
            $detachUserFromCustomer->handle($user->customer);
            Flux::toast(__('messages.customer_unlinked'), variant: 'success');
        } catch (RoleChangeException $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        }

        Flux::modal('user-unlink-customer')->close();
        $this->unlinkingId = null;
        unset($this->users);
    }

    public function confirmDelete(int $userId): void
    {
        $this->authorize('users.destroy');
        $this->deletingId = $userId;

        Flux::modal('user-delete')->show();
    }

    public function delete(DeleteUser $deleteUser): void
    {
        $actor = auth()->user();

        abort_unless($actor instanceof User, 403);
        $this->authorize('users.destroy');

        try {
            $deleteUser->handle(User::findOrFail($this->deletingId), $actor);
            Flux::toast(__('messages.user_deleted'), variant: 'success');
        } catch (CannotDeleteRecordException $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        }

        Flux::modal('user-delete')->close();
        $this->deletingId = null;
        unset($this->users);
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('messages.users')" :subtitle="__('messages.managers_professionals_and_customers_with_access')">
        @if ($this->creatableRoles !== [])
            <x-slot:actions>
                <flux:dropdown position="bottom" align="end">
                    <flux:button variant="primary" icon="plus" icon:trailing="chevron-down">{{ __('messages.new_user') }}</flux:button>

                    <flux:menu>
                        @foreach ($this->creatableRoles as $creatable)
                            <flux:menu.item :icon="$creatable['icon']" x-on:click="$dispatch('{{ $creatable['event'] }}', { role: '{{ $creatable['role'] }}' })">{{ $creatable['label'] }}</flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>
            </x-slot:actions>
        @endif
    </x-page-header>

    <x-data-table :search-placeholder="__('messages.search_by_name_email_or_phone')" :active-filters="$this->activeFilters">
        <x-slot:filters>
            <flux:select wire:model="filters.role" :label="__('messages.role')">
                <flux:select.option value="">{{ __('messages.all') }}</flux:select.option>
                @foreach ($this->roles as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </x-slot:filters>

        @if ($this->users->isEmpty())
            <x-data-table.empty :heading="__('messages.no_users_found')" :description="__('messages.adjust_search_or_add_user')" icon="identification" :filtering="$this->activeFilters !== []" />
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('messages.user') }}</flux:table.column>
                    <flux:table.column>{{ __('messages.role') }}</flux:table.column>
                    <flux:table.column>{{ __('messages.phone') }}</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">{{ __('messages.actions') }}</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->users as $user)
                        <flux:table.row :key="$user->id">
                            <flux:table.cell class="py-3">
                                <div class="flex items-center gap-3">
                                    <flux:avatar size="sm" circle :src="$user->avatar_url" :name="$user->name" />
                                    <div class="flex min-w-0 flex-col">
                                        <flux:text variant="strong" class="font-medium">{{ $user->name }}</flux:text>
                                        <flux:text size="sm">{{ $user->email }}</flux:text>
                                    </div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    <flux:badge size="sm" inset="top bottom">{{ $user->mainRole()?->label() }}</flux:badge>
                                    @if ($user->isLinkedCustomer())
                                        <flux:badge size="sm" inset="top bottom" color="green">{{ __('messages.customer') }}</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $user->customer?->formatted_phone ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @canany(['users.update', 'professionals.update', 'users.link-customer', 'users.destroy'])
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('messages.actions').': '.$user->name" />

                                        <flux:menu>
                                            @can('users.update')
                                                <flux:menu.item icon="pencil-square" x-on:click="$dispatch('open-user-form', { userId: {{ $user->id }} })">{{ __('messages.edit') }}</flux:menu.item>
                                            @endcan
                                            @if ($user->professional?->is_active)
                                                @can('professionals.update')
                                                    <flux:menu.item icon="scissors" x-on:click="$dispatch('open-professional-form', { professionalId: {{ $user->professional->id }} })">{{ __('messages.edit_professional') }}</flux:menu.item>
                                                @endcan
                                            @endif
                                            @can('users.link-customer')
                                                @if ($user->isLinkedCustomer())
                                                    <flux:menu.item icon="user-minus" wire:click="confirmUnlinkCustomer({{ $user->id }})">{{ __('messages.unlink_customer') }}</flux:menu.item>
                                                @elseif ($user->canBeLinkedAsCustomer())
                                                    <flux:menu.item icon="user-plus" wire:click="confirmLinkCustomer({{ $user->id }})">{{ __('messages.link_as_customer') }}</flux:menu.item>
                                                @endif
                                            @endcan
                                            @unless ($user->is(auth()->user()))
                                                @can('users.destroy')
                                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $user->id }})">{{ __('messages.delete') }}</flux:menu.item>
                                                @endcan
                                            @endunless
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
                    :from="$this->users->firstItem()"
                    :to="$this->users->lastItem()"
                    :total="$this->users->total()"
                    :current-page="$this->users->currentPage()"
                    :last-page="$this->users->lastPage()"
                />
            </x-slot:footer>
        @endif
    </x-data-table>

    <flux:modal name="user-delete" class="w-full p-0! md:w-md" :closable="false">
        <div class="flex items-center gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <flux:avatar icon="trash" color="red" class="shrink-0" />

            <div class="min-w-0 flex-1">
                <flux:heading size="lg">{{ __('messages.delete_user') }}</flux:heading>
                <flux:text>{{ __('messages.account_loses_access_immediately') }}</flux:text>
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

    <flux:modal name="user-link-customer" class="w-full p-0! md:w-lg md:max-w-lg" :closable="false">
        <form wire:submit="linkCustomer" class="flex max-h-[calc(100dvh-4rem)] flex-col">
            <div class="flex items-center gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:avatar icon="user-plus" class="shrink-0 bg-table-header text-table-header-content" />

                <div class="min-w-0 flex-1">
                    <flux:heading size="lg">{{ __('messages.link_as_customer') }}</flux:heading>
                    <flux:text>{{ __('messages.mobile_links_existing_customer') }}</flux:text>
                </div>

                <flux:modal.close>
                    <flux:button variant="ghost" size="sm" icon="x-mark" :aria-label="__('messages.close')" />
                </flux:modal.close>
            </div>

            <div class="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto p-6">
                <flux:input wire:model="linkPhone" :label="__('messages.mobile')" type="tel" mask="(99) 99999-9999" :placeholder="__('messages.phone_placeholder')" required />
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('messages.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="linkCustomer">{{ __('messages.link_as_customer') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="user-unlink-customer" class="w-full p-0! md:w-md" :closable="false">
        <div class="flex items-center gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <flux:avatar icon="user-minus" class="shrink-0 bg-table-header text-table-header-content" />

            <div class="min-w-0 flex-1">
                <flux:heading size="lg">{{ __('messages.unlink_customer') }}</flux:heading>
                <flux:text>{{ __('messages.account_loses_customer_role_record_kept') }}</flux:text>
            </div>

            <flux:modal.close>
                <flux:button variant="ghost" size="sm" icon="x-mark" :aria-label="__('messages.close')" />
            </flux:modal.close>
        </div>

        <div class="flex items-center justify-end gap-2 px-6 py-4">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('messages.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="primary" wire:click="unlinkCustomer" wire:loading.attr="disabled" wire:target="unlinkCustomer">{{ __('messages.unlink') }}</flux:button>
        </div>
    </flux:modal>

    <livewire:user-form />

    @canany(['professionals.create', 'professionals.update'])
        <livewire:professional-form />
    @endcanany
</div>
