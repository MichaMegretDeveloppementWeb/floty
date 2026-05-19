<?php

declare(strict_types=1);

namespace App\Services\Billing\Discount;

use App\Models\RentalDiscount;

/**
 * In-memory `(vehicleId, ISO date) → RentalDiscount|null` index
 * precomputed for one `(company × year)` (or for a multi-company
 * batch). The « empty pivot = every vehicle » semantic is handled at
 * preload · a discount with no pivot is recorded under the wildcard
 * bucket.
 *
 * O(1) lookups via {@see findFor()}. Designed to be built once per
 * `(company × year)` and queried in a loop by the
 * {@see DiscountApplier} without further SQL.
 */
final class ResolvedDiscountIndex
{
    /**
     * Discounts attached to explicit vehicles, indexed as
     * `vehicleId → list<RentalDiscount>`. Sorted by `start_date` for
     * deterministic ordering · uniqueness is guaranteed by
     * `RentalDiscountConflictService`, so the first one matching a
     * given date wins.
     *
     * @var array<int, list<RentalDiscount>>
     */
    private array $byVehicle = [];

    /**
     * "All vehicles" discounts (empty pivot) for the
     * `(company × year)`, sorted by `start_date`.
     *
     * @var list<RentalDiscount>
     */
    private array $allVehicles = [];

    private bool $empty = true;

    /**
     * @param  iterable<int, RentalDiscount>  $discounts  Must come with the `vehicles` relation eager-loaded.
     */
    public static function fromDiscounts(iterable $discounts): self
    {
        $index = new self;

        foreach ($discounts as $discount) {
            $index->empty = false;

            $vehicleIds = $discount->vehicles->pluck('id')->all();

            if ($vehicleIds === []) {
                $index->allVehicles[] = $discount;

                continue;
            }

            foreach ($vehicleIds as $vehicleId) {
                $index->byVehicle[(int) $vehicleId][] = $discount;
            }
        }

        return $index;
    }

    public static function empty(): self
    {
        return new self;
    }

    /**
     * Active discount for `(vehicleId, dateStr)`, or `null`.
     *
     * Precedence · `RentalDiscountConflictService` forbids overlap
     * between an « all vehicles » discount and a subset discount on
     * the same date, so the first match is unambiguous.
     */
    public function findFor(int $vehicleId, string $date): ?RentalDiscount
    {
        foreach ($this->byVehicle[$vehicleId] ?? [] as $discount) {
            if ($this->dateInDiscount($date, $discount)) {
                return $discount;
            }
        }

        foreach ($this->allVehicles as $discount) {
            if ($this->dateInDiscount($date, $discount)) {
                return $discount;
            }
        }

        return null;
    }

    /**
     * Hot path · lets consumers fully skip the discount pipeline when
     * no discount is active, guaranteeing byte-identical equivalence
     * with the pre-discount pipeline.
     */
    public function isEmpty(): bool
    {
        return $this->empty;
    }

    private function dateInDiscount(string $date, RentalDiscount $discount): bool
    {
        $start = $discount->start_date->toDateString();
        $end = $discount->end_date->toDateString();

        return $date >= $start && $date <= $end;
    }
}
