<?php

declare(strict_types=1);

namespace App\Data\User\Billing;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Récap annuel par mois civil, pour la fiche véhicule ou la fiche
 * entreprise (Phase 14.D V1.2).
 *
 * **Toujours 12 entrées** dans `entries` (mois 1 → 12), même pour
 * les mois sans utilisation : la grille affichage reste stable.
 *
 * `yearTotalCents` est `null` si **au moins un mois** a un tarif
 * manquant (`hasMissingPricing = true`) : on ne peut alors pas calculer
 * un cumul exact. Le frontend affichera un total partiel ou un message
 * « total indisponible » selon le contexte.
 */
#[TypeScript]
final class MonthlyBillingBreakdownData extends Data
{
    /**
     * @param  list<MonthlyBreakdownEntryData>  $entries  Toujours de longueur 12
     *                                                    (un objet par mois civil,
     *                                                    janvier en première position).
     */
    public function __construct(
        public int $year,
        #[DataCollectionOf(MonthlyBreakdownEntryData::class)]
        public array $entries,
        public int $yearTotalDaysUsed,
        public ?int $yearTotalCents,
        public bool $hasAnyMissingPricing,
    ) {}
}
