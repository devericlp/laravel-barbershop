<?php

use App\Enums\UserRole;
use App\Http\Middleware\EnsureRoleIsSelected;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::auth')] class extends Component
{
    public function mount(): void
    {
        if (! $this->user()->hasMultipleRoles()) {
            $this->redirect($this->user()->homeUrl(), navigate: true);
        }
    }

    public function rendering(View $view): void
    {
        $view->title(__('messages.how_do_you_want_to_sign_in'));
    }

    /** @return list<UserRole> */
    #[Computed]
    public function roles(): array
    {
        return $this->user()->userRoles();
    }

    public function select(string $role): void
    {
        $selected = UserRole::tryFrom($role);

        abort_unless(in_array($selected, $this->roles, true), 403);

        session()->put(EnsureRoleIsSelected::SESSION_KEY, $selected->value);

        $this->redirectIntended($this->user()->homeUrl(), navigate: true);
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
};
?>

<div class="flex flex-col gap-6">
    <div class="text-center">
        <flux:heading size="xl" class="text-2xl! font-bold!">{{ __('messages.how_do_you_want_to_sign_in') }}</flux:heading>
        <flux:text class="mt-2">{{ __('messages.choose_how_to_use_app_now', ['app' => config('app.name')]) }}</flux:text>
    </div>

    <div class="flex flex-col gap-4">
        @foreach ($this->roles as $role)
            <flux:card wire:key="role-{{ $role->value }}" class="flex items-center gap-4 p-4! transition hover:border-accent-content/40 hover:bg-sand-50 dark:hover:bg-white/15">
                <div class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-sand-200 text-accent-content dark:bg-zinc-700">
                    <flux:icon :name="$role->icon()" class="size-7" />
                </div>

                <div class="min-w-0 flex-1">
                    <flux:heading size="lg" class="font-bold!">{{ $role->label() }}</flux:heading>
                    <flux:text class="mt-1">{{ $role->description() }}</flux:text>
                </div>

                <flux:button variant="ghost" icon="chevron-right" wire:click="select('{{ $role->value }}')" :aria-label="$role->label()" class="text-accent-content! after:absolute after:inset-0" />
            </flux:card>
        @endforeach
    </div>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf

        <flux:link as="button" type="submit" class="text-sm">{{ __('messages.log_out_of_your_account') }}</flux:link>
    </form>
</div>
