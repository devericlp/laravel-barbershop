<?php

use App\Actions\Customers\DeleteCustomer;
use App\Exceptions\CannotDeleteRecordException;
use App\Livewire\DataTableComponent;
use App\Models\Customer;
use App\Support\PhoneNumber;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

new class extends DataTableComponent
{
    public ?int $deletingId = null;

    public function rendering(View $view): void
    {
        $view->title(__('messages.customers'));
    }

    /** @return LengthAwarePaginator<int, Customer> */
    #[Computed]
    public function customers(): LengthAwarePaginator
    {
        $search = $this->searchTerm() ?? '';
        $digits = PhoneNumber::normalize($search);
        $account = $this->appliedFilter('account');

        return Customer::with('user')
            ->withCount('bookings')
            ->when($account === 'with', fn (Builder $query) => $query->whereNotNull('user_id'))
            ->when($account === 'without', fn (Builder $query) => $query->whereNull('user_id'))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->when($digits !== '', fn (Builder $phone) => $phone->orWhere('phone', 'like', "%{$digits}%"))))
            ->orderByName()
            ->paginate($this->perPage);
    }

    /** @return array<string, array{label: string, options: array<string, string>}> */
    protected function filterDefinitions(): array
    {
        return [
            'account' => [
                'label' => __('messages.account'),
                'options' => ['with' => __('messages.with_account'), 'without' => __('messages.without_account')],
            ],
        ];
    }

    #[On('customer-saved')]
    public function refreshCustomers(): void
    {
        unset($this->customers);
    }

    public function confirmDelete(int $customerId): void
    {
        $this->authorize('customers.destroy');
        $this->deletingId = $customerId;

        Flux::modal('customer-delete')->show();
    }

    public function delete(DeleteCustomer $deleteCustomer): void
    {
        $this->authorize('customers.destroy');

        try {
            $deleteCustomer->handle(Customer::findOrFail($this->deletingId));
            Flux::toast(__('messages.customer_deleted'), variant: 'success');
        } catch (CannotDeleteRecordException $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        }

        Flux::modal('customer-delete')->close();
        $this->deletingId = null;
        unset($this->customers);
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('messages.customers')" :subtitle="__('messages.customers_served_with_or_without_account')">
        @can('customers.create')
            <x-slot:actions>
                <flux:button variant="primary" icon="plus" x-on:click="$dispatch('open-customer-form')">{{ __('messages.new_customer') }}</flux:button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-data-table :search-placeholder="__('messages.search_by_name_or_phone')" :active-filters="$this->activeFilters">
        <x-slot:filters>
            <flux:select wire:model="filters.account" :label="__('messages.account')">
                <flux:select.option value="">{{ __('messages.all') }}</flux:select.option>
                <flux:select.option value="with">{{ __('messages.with_account') }}</flux:select.option>
                <flux:select.option value="without">{{ __('messages.without_account') }}</flux:select.option>
            </flux:select>
        </x-slot:filters>

        @if ($this->customers->isEmpty())
            <x-data-table.empty :heading="__('messages.no_customers_found')" :description="__('messages.adjust_search_or_add_customer')" icon="user" :filtering="$this->activeFilters !== []" />
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('messages.customer') }}</flux:table.column>
                    <flux:table.column>{{ __('messages.phone') }}</flux:table.column>
                    <flux:table.column>{{ __('messages.linked_account') }}</flux:table.column>
                    <flux:table.column>{{ __('messages.bookings') }}</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">{{ __('messages.actions') }}</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->customers as $customer)
                        <flux:table.row :key="$customer->id">
                            <flux:table.cell class="py-3">
                                <flux:text variant="strong" class="font-medium">{{ $customer->name }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell>{{ $customer->formatted_phone }}</flux:table.cell>
                            <flux:table.cell>{{ $customer->user?->email ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $customer->bookings_count }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @canany(['customers.update', 'customers.destroy'])
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('messages.actions').': '.$customer->name" />

                                        <flux:menu>
                                            @can('customers.update')
                                                <flux:menu.item icon="pencil-square" x-on:click="$dispatch('open-customer-form', { customerId: {{ $customer->id }} })">{{ __('messages.edit') }}</flux:menu.item>
                                            @endcan
                                            @if ($customer->user_id === null && $customer->bookings_count === 0)
                                                @can('customers.destroy')
                                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $customer->id }})">{{ __('messages.delete') }}</flux:menu.item>
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
                    :from="$this->customers->firstItem()"
                    :to="$this->customers->lastItem()"
                    :total="$this->customers->total()"
                    :current-page="$this->customers->currentPage()"
                    :last-page="$this->customers->lastPage()"
                />
            </x-slot:footer>
        @endif
    </x-data-table>

    <flux:modal name="customer-delete" class="w-full p-0! md:w-md" :closable="false">
        <div class="flex items-center gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <flux:avatar icon="trash" color="red" class="shrink-0" />

            <div class="min-w-0 flex-1">
                <flux:heading size="lg">{{ __('messages.delete_customer') }}</flux:heading>
                <flux:text>{{ __('messages.customer_permanently_removed') }}</flux:text>
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

    @canany(['customers.create', 'customers.update'])
        <livewire:customer-form />
    @endcanany
</div>
