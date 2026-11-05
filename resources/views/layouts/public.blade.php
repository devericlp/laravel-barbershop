<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <x-head :title="$title ?? null" />
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-zinc-800">
        {{ $slot }}

        @persist('toast')
            <flux:toast />
        @endpersist

        @fluxScripts
    </body>
</html>
