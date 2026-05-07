<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Snapshot de comparaison facture émise (figée) vs réalité actuelle
 * recalculée (Phase 14.I+ V1.2). Sert à signaler à l'utilisateur que
 * les données contractuelles ont changé après émission de la facture
 * et qu'une régénération est probablement nécessaire.
 *
 * `currentDaysUsed` / `currentTotalCents` peuvent être `null` si le
 * recalcul actuel est bloqué (ex. tarif annuel manquant pour un
 * véhicule qui était présent à l'émission). Dans ce cas, `hasDivergence`
 * est forcément `true` puisque la facture ne peut plus être recalculée
 * dans les mêmes conditions.
 */
#[TypeScript]
final class InvoiceDivergenceData extends Data
{
    public function __construct(
        public bool $hasDivergence,
        /** Snapshot figé à l'émission de la facture (Σ days_used des lignes). */
        public int $invoicedDaysUsed,
        /** Snapshot figé à l'émission de la facture (total_ht_cents). */
        public int $invoicedTotalCents,
        /** Recalcul à l'instant T sur le périmètre actuel des contrats. */
        public ?int $currentDaysUsed,
        /** Recalcul à l'instant T sur le périmètre actuel des contrats. */
        public ?int $currentTotalCents,
    ) {}
}
