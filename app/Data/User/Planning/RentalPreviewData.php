<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Réponse de l'endpoint `POST /app/planning/preview-rentals` ·
 * **loyer standalone** d'une attribution (location/contrat) cohérent
 * avec la facture finale.
 *
 * Sémantique · le service applique strictement la même logique que la
 * facture mensuelle : split par mois civil → `OptimalRateBreakdown` par
 * mois → application des réductions actives via `DiscountApplier`. Les
 * 3 champs `grossTotalCents`, `discountCents`, `netTotalCents` sont
 * cohérents (`net = gross - discount`).
 *
 * `hasMissingPricing = true` quand au moins un mois couvert par le
 * contrat synthétique n'a pas de tarif annuel saisi pour le véhicule.
 * Dans ce cas, les totaux sont `null` (preview impossible) · l'UI
 * affiche un message incitant à compléter les tarifs.
 *
 * `appliedDiscountLabel` / `appliedDiscountBasisPoints` exposent la
 * réduction DOMINANTE appliquée sur la période (cas dominant unique
 * garanti par `RentalDiscountConflictService`) · `null` si aucune
 * réduction active n'a matché les dates du contrat.
 */
#[TypeScript]
final class RentalPreviewData extends Data
{
    public function __construct(
        public int $daysCount,
        public ?int $grossTotalCents,
        public ?int $discountCents,
        public ?int $netTotalCents,
        public ?string $appliedDiscountLabel,
        public ?int $appliedDiscountBasisPoints,
        public bool $hasMissingPricing,
    ) {}
}
