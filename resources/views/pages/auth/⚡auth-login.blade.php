<?php

use App\Http\Middleware\EnsureRoleIsSelected;
use App\Http\Middleware\SetCurrentTenant;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts::auth')] class extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    #[Locked]
    public string $barbershop = '';

    #[Locked]
    public string $booking = '';

    public function mount(): void
    {
        $this->barbershop = request()->string('barbershop')->toString();
        $this->booking = request()->string('booking')->toString();

        if ($this->booking !== '') {
            redirect()->setIntendedUrl(route('public-bookings.create', $this->bookingQuery()));
        }
    }

    #[Computed]
    public function registerUrl(): string
    {
        return $this->booking !== '' && $this->barbershop !== ''
            ? route('register', $this->bookingQuery())
            : route('public-bookings.create');
    }

    public function rendering(View $view): void
    {
        $view->title(__('messages.log_in'));
    }

    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            $this->reset('password');

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        /** @var User $user */
        $user = Auth::user();

        if ($user->tenant !== null && ! $user->tenant->is_active) {
            Auth::guard('web')->logout();

            $this->reset('password');

            throw ValidationException::withMessages([
                'email' => __('messages.barbershop_access_disabled'),
            ]);
        }

        Session::regenerate();
        Session::put(SetCurrentTenant::SESSION_KEY, $user->tenant_id);
        Session::forget(EnsureRoleIsSelected::SESSION_KEY);

        $this->redirectIntended($user->homeUrl(), navigate: true);
    }

    /** @return array<string, string> */
    private function bookingQuery(): array
    {
        return array_filter(['barbershop' => $this->barbershop, 'booking' => $this->booking]);
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => RateLimiter::availableIn($this->throttleKey()),
            ]),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
};
?>

<div class="flex flex-col gap-6">
    <div class="text-center">
        <flux:heading size="xl" class="text-2xl! font-bold!">{{ __('messages.sign_in') }}</flux:heading>
        <flux:text class="mt-2">{{ __('messages.sign_in_to_your_account_to_continue') }}</flux:text>
    </div>

    <form wire:submit="login" class="flex flex-col gap-5">
        <flux:input wire:model="email" :label="__('messages.email')" :placeholder="__('messages.your_email')" icon="envelope" type="email" required autofocus autocomplete="email" />

        <flux:input wire:model="password" :label="__('messages.password')" :placeholder="__('messages.your_password')" icon="lock-closed" type="password" required autocomplete="current-password" viewable />

        <flux:checkbox wire:model="remember" :label="__('messages.remember_me')" />

        <flux:button type="submit" variant="primary" class="w-full">{{ __('messages.log_in') }}</flux:button>
    </form>

    <flux:text class="text-center">
        {{ __('messages.dont_have_an_account') }}
        <flux:link :href="$this->registerUrl" wire:navigate>{{ __('messages.create_account') }}</flux:link>
    </flux:text>
</div>
