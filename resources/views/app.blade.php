<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <meta name="theme-color" content="#0f172a">

        {{-- Préchargement HTTP/2 limité à app.css + app.ts UNIQUEMENT.

             Incident prod Hostinger 2026-05-17 · `@vite()` émet un header
             HTTP `Link: <chunk>; rel=modulepreload` PAR dépendance
             transitive. Inclure le composant page Inertia
             (`resources/js/pages/{component}.vue`) déclenchait la marche
             du graphe de deps · Vehicle Show (115 deps transitives) générait
             un Link header de ~8 KiB qui, combiné aux autres headers
             (HSTS, CSP, Set-Cookie session), dépassait
             `fastcgi_buffer_size` de nginx Hostinger → 504 immédiat
             (nginx rejette la réponse PHP-FPM avant émission, donc rien
             dans laravel.log et crash en <500 ms).

             Symptôme bizarre · navigation Inertia XHR (X-Inertia: true)
             marchait car la réponse JSON n'évalue jamais ce template
             Blade, donc pas de Link header émis. Seul le full reload
             (rendu HTML) crashait.

             Sacrifice · perte du preload HTTP/2 du composant page (la
             1ère visite découvre les imports pendant l'exécution JS,
             ~50-100 ms supplémentaires). Bénéfice · prod stable sur
             nginx aux buffers contraints (shared hosting).

             Si on migre vers un hébergement avec buffers généreux, on
             pourra ré-activer le preload via la branche commentée
             ci-dessous. --}}
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
