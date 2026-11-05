@props(['role', 'platform' => false, 'bookingUrl' => null])

<flux:sidebar.nav>
    @if ($platform)
        <flux:sidebar.item icon="building-storefront" :href="route('tenants.index')" wire:navigate>{{ __('messages.barbershops') }}</flux:sidebar.item>
        <flux:sidebar.item icon="shield-check" :href="route('access-control.index')" wire:navigate>{{ __('messages.access_control') }}</flux:sidebar.item>
    @else
        @can('dashboard.index')
            <flux:sidebar.item icon="home" :href="route('dashboard')" wire:navigate>{{ __('messages.dashboard') }}</flux:sidebar.item>
        @endcan
        @if ($role === \App\Enums\UserRole::Customer)
            @can('my-bookings.create')
                <flux:sidebar.item icon="plus-circle" :href="route('bookings.create')" wire:navigate>{{ __('messages.book') }}</flux:sidebar.item>
            @endcan
        @endif
        @can('bookings.index')
            <flux:sidebar.item icon="calendar-days" :href="route('bookings.index')" wire:navigate>{{ $role === \App\Enums\UserRole::Professional ? __('messages.my_schedule') : __('messages.schedule') }}</flux:sidebar.item>
        @endcan
        @if ($role === \App\Enums\UserRole::Customer)
            @can('my-bookings.index')
                <flux:sidebar.item icon="calendar-days" :href="route('my-bookings.index')" wire:navigate>{{ __('messages.my_bookings') }}</flux:sidebar.item>
            @endcan
        @endif
        @can('services.index')
            <flux:sidebar.item icon="scissors" :href="route('services.index')" wire:navigate>{{ __('messages.services') }}</flux:sidebar.item>
        @endcan
        @can('professionals.index')
            <flux:sidebar.item icon="users" :href="route('professionals.index')" :current="request()->routeIs('professionals.*')" wire:navigate>{{ __('messages.professionals') }}</flux:sidebar.item>
        @endcan
        @can('customers.index')
            <flux:sidebar.item icon="user-group" :href="route('customers.index')" wire:navigate>{{ __('messages.customers') }}</flux:sidebar.item>
        @endcan
        @can('users.index')
            <flux:sidebar.item icon="identification" :href="route('users.index')" wire:navigate>{{ __('messages.users') }}</flux:sidebar.item>
        @endcan
        @if ($bookingUrl)
            <flux:sidebar.item icon="arrow-top-right-on-square" :href="$bookingUrl" target="_blank" rel="noopener">{{ __('messages.open_booking_page') }}</flux:sidebar.item>
        @endif
    @endif
</flux:sidebar.nav>
