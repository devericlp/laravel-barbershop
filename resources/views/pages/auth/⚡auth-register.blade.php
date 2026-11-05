<?php

use App\Actions\Users\CreateUserWithRole;
use App\Enums\UserRole;
use App\Http\Middleware\EnsureRoleIsSelected;
use App\Http\Middleware\SetCurrentTenant;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use App\Support\PhoneNumber;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::auth')] class extends Component
{
    #[Locked]
    public ?int $tenantId = null;

    #[Locked]
    public string $booking = '';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public function mount(): void
    {
        $this->tenantId = Tenant::query()->acceptingCustomers()->firstWhere('slug', request()->string('barbershop')->toString())?->id;
        $this->booking = request()->string('booking')->toString();

        if (! $this->barbershop) {
            $this->redirectRoute('public-bookings.create', navigate: true);

            return;
        }

        app(CurrentTenant::class)->set($this->barbershop);
    }

    public function hydrate(): void
    {
        app(CurrentTenant::class)->set($this->barbershop);
    }

    public function rendering(View $view): void
    {
        $view->title(__('messages.create_account'));
    }

    #[Computed]
    public function barbershop(): ?Tenant
    {
        return $this->tenantId
            ? Tenant::query()->acceptingCustomers()->find($this->tenantId)
            : null;
    }

    /** @return array<string, string> */
    #[Computed]
    public function bookingQuery(): array
    {
        return array_filter(['barbershop' => (string) $this->barbershop?->slug, 'booking' => $this->booking]);
    }

    public function register(CreateUserWithRole $createUserWithRole): void
    {
        if (! $this->barbershop) {
            $this->redirectRoute('public-bookings.create', navigate: true);

            return;
        }

        $this->validate();
        $this->ensureIsNotRateLimited();

        RateLimiter::hit($this->throttleKey(), 3600);

        $user = $createUserWithRole->handle($this->name, $this->email, $this->password, UserRole::Customer, $this->phone);

        event(new Registered($user));

        Auth::login($user);
        Session::regenerate();
        Session::put(SetCurrentTenant::SESSION_KEY, $user->tenant_id);
        Session::forget(EnsureRoleIsSelected::SESSION_KEY);

        $this->redirectRoute('bookings.create', $this->bookingQuery, navigate: true);
    }

    /** @return array<string, list<mixed>> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', function (string $attribute, string $value, Closure $fail) {
                if (! PhoneNumber::isValidMobile($value)) {
                    $fail(__('messages.enter_brazilian_mobile'));
                }
            }],
            'password' => ['required', 'string', Password::defaults()],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('messages.name'),
            'email' => __('messages.email'),
            'phone' => __('messages.mobile'),
            'password' => __('messages.password'),
        ];
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($this->throttleKey())]),
        ]);
    }

    private function throttleKey(): string
    {
        return 'register|'.request()->ip();
    }
};
?>

<div class="flex flex-col gap-6">
    <div class="text-center">
        <flux:heading size="xl" class="text-2xl! font-bold!">{{ __('messages.create_account') }}</flux:heading>
        <flux:text class="mt-2">{{ __('messages.create_account_to_book_at', ['barbershop' => $this->barbershop?->name]) }}</flux:text>
    </div>

    <form wire:submit="register" class="flex flex-col gap-5">
        <flux:input wire:model="name" :label="__('messages.name')" icon="user" required autofocus autocomplete="name" />

        <flux:input wire:model="email" :label="__('messages.email')" :placeholder="__('messages.your_email')" icon="envelope" type="email" required autocomplete="email" />

        <flux:input wire:model="phone" :label="__('messages.mobile')" :placeholder="__('messages.phone_placeholder')" icon="device-phone-mobile" type="tel" mask="(99) 99999-9999" required autocomplete="tel" />

        <flux:input wire:model="password" :label="__('messages.password')" :placeholder="__('messages.your_password')" icon="lock-closed" type="password" required autocomplete="new-password" viewable />

        <flux:button type="submit" variant="primary" class="w-full">{{ __('messages.create_account') }}</flux:button>
    </form>

    <flux:text class="text-center">
        {{ __('messages.already_have_an_account') }}
        <flux:link :href="route('login', $this->bookingQuery)" wire:navigate>{{ __('messages.log_in') }}</flux:link>
    </flux:text>
</div>
