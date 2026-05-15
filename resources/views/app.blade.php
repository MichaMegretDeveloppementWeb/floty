<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- Préchargement HTTP/2 du composant page Inertia · on exclut les
             pages `Dev/*` (UI Kit, demos) du preload car elles ne sont pas
             embarquées dans le bundle production (cf. `app.ts` resolver
             qui filtre `pages/Dev/**` via `import.meta.env.PROD`). En
             local, ces pages restent accessibles via vite dev server. --}}
        @php
            $vitePreloads = ['resources/css/app.css', 'resources/js/app.ts'];
            if (! str_starts_with($page['component'], 'Dev/')) {
                $vitePreloads[] = "resources/js/pages/{$page['component']}.vue";
            }
        @endphp
        @vite($vitePreloads)
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
