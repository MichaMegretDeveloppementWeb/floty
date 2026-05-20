<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\RentalDiscount;
use App\Services\Invoice\InvoiceDivergenceFlagger;

/**
 * Flags the impacted invoices as divergent on every {@see RentalDiscount}
 * mutation. Wired through `#[ObservedBy([RentalDiscountObserver::class])]`.
 *
 * Only field changes that affect the commercial scope (`start_date`,
 * `end_date`, `discount_basis_points`, `company_id`) trigger a flag;
 * purely annotative changes (`label`, `notes`) are skipped to avoid
 * false positives.
 *
 * Per ADR-0008, `is_divergent` is an observability flag only · the
 * frozen invoice/invoice-line columns (`total_ht_cents`,
 * `gross_total_cents`, `discount_cents`, …) are never mutated here.
 *
 * Pivot changes on the vehicles relation (`syncVehicles`) do not trigger
 * Eloquent events on the parent model and are handled by the update
 * action itself when the CRUD module wires them.
 */
final class RentalDiscountObserver
{
    private const array IMPACTING_FIELDS = [
        'start_date',
        'end_date',
        'discount_basis_points',
        'company_id',
    ];

    public function __construct(
        private readonly InvoiceDivergenceFlagger $flagger,
    ) {}

    /**
     * Flag invoices that overlap the discount period on creation.
     */
    public function created(RentalDiscount $discount): void
    {
        $this->flagger->flagForDiscountPeriod(
            $discount->company_id,
            $discount->start_date->toDateString(),
            $discount->end_date->toDateString(),
        );
    }

    /**
     * Flag invoices on update only when an impacting field changed; both
     * the previous and the new periods are covered to catch period shifts.
     */
    public function updated(RentalDiscount $discount): void
    {
        if (! $discount->wasChanged(self::IMPACTING_FIELDS)) {
            return;
        }

        $oldCompanyId = (int) ($discount->getOriginal('company_id') ?? $discount->company_id);
        $newCompanyId = (int) $discount->company_id;
        $oldStart = $this->dateToString($discount->getOriginal('start_date'));
        $oldEnd = $this->dateToString($discount->getOriginal('end_date'));
        $newStart = $discount->start_date->toDateString();
        $newEnd = $discount->end_date->toDateString();

        if ($oldCompanyId === $newCompanyId) {
            $this->flagger->flagForDiscountPeriod(
                $newCompanyId,
                $newStart,
                $newEnd,
                $oldStart,
                $oldEnd,
            );
        } else {
            $this->flagger->flagForDiscountPeriod($oldCompanyId, $oldStart, $oldEnd);
            $this->flagger->flagForDiscountPeriod($newCompanyId, $newStart, $newEnd);
        }
    }

    /**
     * Flag invoices on soft delete so they reflect the disappearance of
     * the discount.
     */
    public function deleted(RentalDiscount $discount): void
    {
        $this->flagger->flagForDiscountPeriod(
            $discount->company_id,
            $discount->start_date->toDateString(),
            $discount->end_date->toDateString(),
        );
    }

    /**
     * Flag invoices on restoration so they reflect the reappearance of
     * the discount.
     */
    public function restored(RentalDiscount $discount): void
    {
        $this->flagger->flagForDiscountPeriod(
            $discount->company_id,
            $discount->start_date->toDateString(),
            $discount->end_date->toDateString(),
        );
    }

    /**
     * Flag invoices on hard delete.
     */
    public function forceDeleted(RentalDiscount $discount): void
    {
        $this->flagger->flagForDiscountPeriod(
            $discount->company_id,
            $discount->start_date->toDateString(),
            $discount->end_date->toDateString(),
        );
    }

    /**
     * Normalise a date value coming from `getOriginal()` to a `Y-m-d`
     * string regardless of whether the cast already ran.
     */
    private function dateToString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }
}
