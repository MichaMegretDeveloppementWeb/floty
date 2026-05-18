<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use App\Models\InvoiceLine;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Ligne d'une facture (snapshot immuable · Phase 14.E V1.2).
 *
 * Tous les champs reflètent l'état au moment de l'émission ; ne
 * suivent pas les mutations ultérieures du véhicule ou de ses tarifs.
 *
 * **Lot 3 réductions commerciales** · ajoute `grossTotalCents`,
 * `discountCents`, `appliedDiscountId` (FK navigable tant que la
 * réduction existe encore) et les snapshots `appliedDiscountLabel` /
 * `appliedDiscountBasisPoints` (figés pour rester exacts même après
 * renommage de la réduction). `totalHtCents` reste le NET (= gross -
 * discount), rétro-compat 100 %.
 */
#[TypeScript]
final class InvoiceLineData extends Data
{
    public function __construct(
        public int $id,
        public int $vehicleId,
        public string $vehicleLabelSnapshot,
        public int $daysUsed,
        public int $monthsBilled,
        public int $weeksBilled,
        public int $daysBilled,
        public int $dailyRateCents,
        public int $weeklyRateCents,
        public int $monthlyRateCents,
        public int $totalHtCents,
        public int $grossTotalCents,
        public int $discountCents,
        public ?int $appliedDiscountId,
        public ?string $appliedDiscountLabel,
        public ?int $appliedDiscountBasisPoints,
    ) {}

    public static function fromModel(InvoiceLine $line): self
    {
        return new self(
            id: $line->id,
            vehicleId: $line->vehicle_id,
            vehicleLabelSnapshot: $line->vehicle_label_snapshot,
            daysUsed: $line->days_used,
            monthsBilled: $line->months_billed,
            weeksBilled: $line->weeks_billed,
            daysBilled: $line->days_billed,
            dailyRateCents: $line->daily_rate_cents,
            weeklyRateCents: $line->weekly_rate_cents,
            monthlyRateCents: $line->monthly_rate_cents,
            totalHtCents: $line->total_ht_cents,
            grossTotalCents: $line->gross_total_cents,
            discountCents: $line->discount_cents,
            appliedDiscountId: $line->applied_discount_id,
            appliedDiscountLabel: $line->applied_discount_label_snapshot,
            appliedDiscountBasisPoints: $line->applied_discount_basis_points_snapshot,
        );
    }
}
