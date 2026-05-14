<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\LogLockoutAttempt;
use Illuminate\Support\ServiceProvider;

/**
 * Provider réservé aux listeners d'événements applicatifs.
 *
 * Stratégie d'enregistrement · Floty s'appuie sur l'auto-discovery
 * Laravel 11+ qui scanne `app/Listeners/` et enregistre chaque listener
 * à partir du type-hint de sa méthode `handle()`. Aucun mapping explicite
 * n'est nécessaire ici tant que les listeners respectent la convention.
 *
 * Ne PAS doubler avec un `Event::listen()` ou `$listen[]` · ça produit
 * 2 enregistrements pour un même event et le listener fire 2 fois.
 *
 * Le Provider reste pour cas futurs · listener sans convention,
 * subscriber, ou listener avec dépendance d'enregistrement custom.
 *
 * Cf. plan-remédiation Vague 1 Lot 1 D2 (F-10-002) ·
 * {@see LogLockoutAttempt} couvre les Lockout events.
 */
final class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Auto-discovery (Laravel 11+) gère app/Listeners. Pas de mapping ici.
    }
}
