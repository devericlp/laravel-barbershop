<?php

use App\Actions\Users\ChangeUserRole;
use App\Actions\Users\CreateUserWithRole;
use App\Actions\Users\UpdateUserWithRole;
use App\Enums\UserRole;
use App\Exceptions\RoleChangeException;
use App\Exceptions\RoleNotEnabledException;
use App\Models\User;
use App\Support\CurrentTenant;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = '';

    #[On('open-user-form')]
    public function open(?string $role = null, ?int $userId = null): void
    {
        $this->authorize($userId === null ? 'users.create' : 'users.update');
        $this->reset();
        $this->resetValidation();

        if ($userId !== null) {
            $user = User::with('roles')->findOrFail($userId);

            $this->userId = $user->id;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->role = (string) $user->mainRole()?->value;
        } elseif (UserRole::tryFrom((string) $role) !== null) {
            $this->role = (string) $role;
        }

        Flux::modal('user-form')->show();
    }

    /** @return list<UserRole> */
    #[Computed]
    public function roleOptions(): array
    {
        $tenant = app(CurrentTenant::class)->get();

        return array_values(array_filter(
            ChangeUserRole::ASSIGNABLE,
            fn (UserRole $role) => $role !== UserRole::Customer && ((bool) $tenant?->enablesRole($role) || $role->value === $this->role),
        ));
    }

    public function save(CreateUserWithRole $createUserWithRole, UpdateUserWithRole $updateUserWithRole): void
    {
        $this->authorize($this->userId === null ? 'users.create' : 'users.update');

        $this->validate($this->validationRules(), attributes: [
            'name' => __('messages.name'),
            'email' => __('messages.email'),
            'password' => __('messages.password'),
            'role' => __('messages.role'),
        ]);

        $user = $this->userId ? User::with(['professional', 'roles'])->findOrFail($this->userId) : null;
        $role = UserRole::from($this->role);
        $becomesProfessional = $role === UserRole::Professional && $user?->mainRole() !== UserRole::Professional;

        if ($becomesProfessional) {
            $this->authorize('professionals.create');
        }

        $name = trim($this->name);

        try {
            $saved = $user
                ? $updateUserWithRole->handle($user, $name, $this->email, $this->password ?: null, $role)
                : $createUserWithRole->handle($name, $this->email, $this->password, $role);
        } catch (RoleNotEnabledException|RoleChangeException $exception) {
            $this->addError('role', $exception->getMessage());

            return;
        }

        Flux::modal('user-form')->close();
        Flux::toast($user ? __('messages.user_updated') : __('messages.user_created'), variant: 'success');

        $this->dispatch('user-saved');

        $professionalId = $becomesProfessional ? $saved->professional()->value('id') : null;

        if ($professionalId !== null) {
            $this->dispatch('open-professional-form', professionalId: $professionalId, step: 2);
        }
    }

    /** @return array<string, mixed> */
    private function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->userId)],
            'password' => [$this->userId ? 'nullable' : 'required', 'string', Password::defaults()],
            'role' => ['required', 'string', Rule::in(array_map(fn (UserRole $role) => $role->value, $this->roleOptions))],
        ];
    }
};
?>

<div>
    <flux:modal name="user-form" class="w-full p-0! md:w-2xl md:max-w-2xl" :closable="false">
        <form wire:submit="save" class="flex max-h-[calc(100dvh-4rem)] flex-col">
            <div class="flex items-center gap-3 border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:avatar icon="identification" class="shrink-0 bg-table-header text-table-header-content" />

                <div class="min-w-0 flex-1">
                    <flux:heading size="lg">{{ $userId ? __('messages.edit_user') : __('messages.new_user') }}</flux:heading>
                    <flux:text>{{ $userId ? __('messages.update_user_access_details') : __('messages.set_initial_password_and_share') }}</flux:text>
                </div>

                <flux:modal.close>
                    <flux:button variant="ghost" size="sm" icon="x-mark" :aria-label="__('messages.close')" />
                </flux:modal.close>
            </div>

            <div class="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto p-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="name" :label="__('messages.name')" required />

                    <flux:input wire:model="email" :label="__('messages.email')" type="email" autocomplete="off" required />

                    <flux:input wire:model="password" :label="__('messages.password')" :description="$userId ? __('messages.leave_blank_to_keep_password') : __('messages.initial_sign_in_password')" type="password" autocomplete="new-password" viewable :required="! $userId" />
                </div>

                <flux:select wire:model="role" :label="__('messages.role')" :placeholder="__('messages.select')">
                    @foreach ($this->roleOptions as $option)
                        <flux:select.option :value="$option->value" wire:key="role-option-{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('messages.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('messages.save_user') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
