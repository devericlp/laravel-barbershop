<?php

use App\Enums\UserRole;
use App\Http\Middleware\SetCurrentTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public const string PLATFORM = 'admin';

    public string $view = self::PLATFORM;

    public function mount(): void
    {
        $this->ensureIsAdmin();

        $this->view = (string) (app(CurrentTenant::class)->id() ?? self::PLATFORM);
    }

    /** @return Collection<int, Tenant> */
    #[Computed]
    public function tenants(): Collection
    {
        return Tenant::orderBy('name')->get();
    }

    public function updatedView(): void
    {
        $this->ensureIsAdmin();

        $tenant = $this->view === self::PLATFORM ? null : Tenant::find((int) $this->view);

        if ($tenant === null) {
            session()->forget(SetCurrentTenant::SESSION_KEY);
        } else {
            session()->put(SetCurrentTenant::SESSION_KEY, $tenant->id);
        }

        app(CurrentTenant::class)->set($tenant);

        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        $this->redirect($user->homeUrl());
    }

    private function ensureIsAdmin(): void
    {
        abort_unless(auth()->user()?->hasUserRole(UserRole::Admin), 403);
    }
};
?>

<div class="in-data-flux-sidebar-collapsed-desktop:hidden">
    <flux:select wire:model.live="view" size="sm" :label="__('messages.view')">
        <flux:select.option value="admin">{{ __('messages.admin') }}</flux:select.option>
        @foreach ($this->tenants as $tenant)
            <flux:select.option :value="(string) $tenant->id" wire:key="view-tenant-{{ $tenant->id }}">{{ $tenant->name }}</flux:select.option>
        @endforeach
    </flux:select>
</div>
