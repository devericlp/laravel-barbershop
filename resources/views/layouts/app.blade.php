<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <x-head :title="$title ?? null" />
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-zinc-800">
        <flux:sidebar sticky collapsible class="dark w-72 overflow-x-hidden border-e border-zinc-800 bg-zinc-900 lg:transition-[width,padding] lg:duration-300 lg:ease-in-out motion-reduce:transition-none">
            <flux:sidebar.header class="h-16">
                <flux:sidebar.brand :href="route('home')">
                    <x-slot:logo class="h-16 rounded-none">
                        <img src="{{ asset('images/logo_full.png') }}" alt="{{ config('app.name') }}" class="h-16 w-auto max-w-none transition-opacity duration-300 starting:opacity-0 in-data-flux-sidebar-collapsed-desktop:hidden" />
                        <img src="{{ asset('images/logo_mini.png') }}" alt="{{ config('app.name') }}" class="hidden size-10 transition-opacity duration-300 starting:opacity-0 in-data-flux-sidebar-collapsed-desktop:block" />
                    </x-slot:logo>
                </flux:sidebar.brand>

                <flux:sidebar.collapse />
            </flux:sidebar.header>

            @if (auth()->user()->hasUserRole(\App\Enums\UserRole::Admin))
                <livewire:view-switcher />
            @elseif (auth()->user()->hasMultipleRoles())
                <livewire:role-switcher />
            @endif

            <x-app-nav :role="auth()->user()->activeRole()" :platform="auth()->user()->viewsPlatform()" :booking-url="auth()->user()->barbershopBookingUrl()" />

            <flux:sidebar.spacer />

            <flux:dropdown position="top" align="start">
                <flux:sidebar.profile :name="auth()->user()->name" :avatar="auth()->user()->avatar_url" :initials="auth()->user()->initials()" />

                <x-user-menu />
            </flux:dropdown>
        </flux:sidebar>

        <flux:header class="gap-3 border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <x-breadcrumbs :title="$title ?? null" />

            <flux:spacer />

            <div class="flex items-center gap-1">
                <x-appearance-dropdown />
                <x-locale-switcher compact />
            </div>
        </flux:header>

        <flux:main>
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast />
        @endpersist

        @fluxScripts
    </body>
</html>
