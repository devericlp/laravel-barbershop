<?php

use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url]
    public string $tab = 'roles';

    public function mount(): void
    {
        if (! in_array($this->tab, ['roles', 'permissions'], true)) {
            $this->tab = 'roles';
        }
    }

    public function rendering(View $view): void
    {
        $view->title(__('messages.access_control'));
    }
};
?>

<div class="flex flex-col gap-6">
    <x-page-header :title="__('messages.access_control')" :subtitle="__('messages.roles_and_permissions_of_platform')">
        <x-slot:actions>
            @if ($tab === 'roles')
                <flux:button variant="primary" icon="plus" x-on:click="$dispatch('create-role')">{{ __('messages.new_role') }}</flux:button>
            @else
                <flux:button variant="primary" icon="plus" x-on:click="$dispatch('create-permission')">{{ __('messages.new_permission') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <flux:tab.group>
        <flux:tabs wire:model.live="tab">
            <flux:tab name="roles" icon="user-group">{{ __('messages.roles') }}</flux:tab>
            <flux:tab name="permissions" icon="key">{{ __('messages.permissions') }}</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="roles">
            @if ($tab === 'roles')
                <livewire:access-control.roles-table />
            @endif
        </flux:tab.panel>

        <flux:tab.panel name="permissions">
            @if ($tab === 'permissions')
                <livewire:access-control.permissions-table />
            @endif
        </flux:tab.panel>
    </flux:tab.group>
</div>