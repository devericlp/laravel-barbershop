<?php

use App\Actions\Customers\CreateCustomer;
use App\Actions\Customers\UpdateCustomer;
use App\Models\Customer;
use App\Support\CurrentTenant;
use App\Support\PhoneNumber;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public ?int $customerId = null;

    public string $name = '';

    public string $phone = '';

    #[On('open-customer-form')]
    public function open(?int $customerId = null): void
    {
        $this->authorize($customerId === null ? 'customers.create' : 'customers.update');
        $this->reset();
        $this->resetValidation();

        if ($customerId !== null) {
            $customer = Customer::findOrFail($customerId);

            $this->customerId = $customer->id;
            $this->name = $customer->name;
            $this->phone = $customer->formatted_phone;
        }

        Flux::modal('customer-form')->show();
    }

    public function save(CreateCustomer $createCustomer, UpdateCustomer $updateCustomer): void
    {
        $this->authorize($this->customerId === null ? 'customers.create' : 'customers.update');

        $this->validate($this->validationRules(), attributes: [
            'name' => __('messages.name'),
            'phone' => __('messages.mobile'),
        ]);

        $name = trim($this->name);
        $customer = $this->customerId ? Customer::with('user')->findOrFail($this->customerId) : null;

        $saved = $customer
            ? $updateCustomer->handle($customer, $name, $this->phone, $customer->user)
            : $createCustomer->handle($name, $this->phone);

        Flux::modal('customer-form')->close();
        Flux::toast($customer ? __('messages.customer_updated') : __('messages.customer_created'), variant: 'success');

        if ($customer === null) {
            $this->dispatch('customer-created', customerId: $saved->id);
        }

        $this->dispatch('customer-saved');
    }

    /** @return array<string, mixed> */
    private function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['required', 'string', function (string $attribute, string $value, Closure $fail) {
                if (! PhoneNumber::isValidMobile($value)) {
                    $fail(__('messages.enter_brazilian_mobile'));

                    return;
                }

                $taken = Rule::unique('customers', 'phone')
                    ->where('tenant_id', app(CurrentTenant::class)->id())
                    ->ignore($this->customerId);

                if (validator(['phone' => PhoneNumber::normalize($value)], ['phone' => $taken])->fails()) {
                    $fail(__('messages.customer_phone_already_exists'));
                }
            }],
        ];
    }
};
?>

<div>
    <flux:modal name="customer-form" class="w-full p-0! md:w-2xl md:max-w-2xl" :closable="false">
        <form wire:submit="save" class="flex max-h-[calc(100dvh-4rem)] flex-col">
            <div class="flex items-center gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:avatar icon="user" class="shrink-0 bg-table-header text-table-header-content" />

                <div class="min-w-0 flex-1">
                    <flux:heading size="lg">{{ $customerId ? __('messages.edit_customer') : __('messages.new_customer') }}</flux:heading>
                    <flux:text>{{ __('messages.name_and_mobile_identify_customer') }}</flux:text>
                </div>

                <flux:modal.close>
                    <flux:button variant="ghost" size="sm" icon="x-mark" :aria-label="__('messages.close')" />
                </flux:modal.close>
            </div>

            <div class="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto p-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="name" :label="__('messages.name')" required />

                    <flux:input wire:model="phone" :label="__('messages.mobile')" type="tel" mask="(99) 99999-9999" :placeholder="__('messages.phone_placeholder')" required />
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('messages.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('messages.save_customer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
