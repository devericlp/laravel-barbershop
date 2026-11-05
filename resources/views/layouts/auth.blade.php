<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <x-head :title="$title ?? null" />
    </head>
    <body class="min-h-screen bg-sand-100 antialiased dark:bg-zinc-900">
        <div class="flex min-h-svh">
            <aside class="relative hidden w-2/5 max-w-xl overflow-hidden lg:flex lg:flex-col lg:justify-end">
                <img src="{{ asset('images/bg_auth.png') }}" alt="" class="absolute inset-0 size-full object-cover" />
                <div class="absolute inset-0 bg-linear-to-b from-transparent via-transparent to-black/80"></div>

                <div class="relative flex flex-col gap-4 p-10">
                    <flux:heading size="xl" class="max-w-xs text-3xl! leading-tight! font-bold! text-white!">
                        {{ __('messages.your_next_appointment_starts_here') }}
                    </flux:heading>
                    <div class="h-1 w-14 rounded-full bg-emerald-400"></div>
                </div>
            </aside>

            <main class="flex flex-1 flex-col items-center justify-center gap-6 p-6">
                <flux:brand :href="route('login')" :name="config('app.name')" class="lg:hidden">
                    <x-slot:logo class="bg-accent text-accent-foreground">
                        <flux:icon.scissors variant="micro" />
                    </x-slot:logo>
                </flux:brand>

                <flux:card class="w-full max-w-md space-y-6 p-8 shadow-sm">
                    <div class="flex justify-center">
                        <div class="flex size-16 items-center justify-center rounded-2xl bg-sand-200 text-accent-content dark:bg-zinc-700">
                            <flux:icon.scissors class="size-8" />
                        </div>
                    </div>

                    {{ $slot }}
                </flux:card>

                <div class="flex items-center justify-center gap-2">
                    <flux:button x-data x-on:click="$flux.dark = ! $flux.dark" variant="ghost" size="sm" square :aria-label="__('messages.theme')">
                        <flux:icon.moon variant="mini" x-show="! $flux.dark" />
                        <flux:icon.sun variant="mini" x-show="$flux.dark" x-cloak />
                    </flux:button>

                    <x-locale-switcher position="top" align="center" compact />
                </div>
            </main>
        </div>

        @fluxScripts
    </body>
</html>
