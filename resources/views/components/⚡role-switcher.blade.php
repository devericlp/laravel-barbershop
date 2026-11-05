<?php

use App\Enums\UserRole;
use App\Http\Middleware\EnsureRoleIsSelected;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $role = '';

    public function mount(): void
    {
        $this->role = (string) $this->user()->activeRole()?->value;
    }

    /** @return list<UserRole> */
    #[Computed]
    public function roles(): array
    {
        return $this->user()->userRoles();
    }

    public function updatedRole(): void
    {
        $selected = UserRole::tryFrom($this->role);

        abort_unless(in_array($selected, $this->roles, true), 403);

        session()->put(EnsureRoleIsSelected::SESSION_KEY, $selected->value);

        $this->redirect($this->user()->homeUrl());
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
};
?>

<div class="in-data-flux-sidebar-collapsed-desktop:hidden">
    <flux:select wire:model.live="role" size="sm" :label="__('messages.role')">
        @foreach ($this->roles as $option)
            <flux:select.option :value="$option->value" wire:key="role-{{ $option->value }}">{{ $option->label() }}</flux:select.option>
        @endforeach
    </flux:select>
</div>
