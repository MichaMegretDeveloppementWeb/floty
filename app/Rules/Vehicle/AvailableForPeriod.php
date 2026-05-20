<?php

declare(strict_types=1);

namespace App\Rules\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects any contract or unavailability period whose dates overlap or
 * exceed `vehicles.exit_date` (ADR-0018 § 5):
 *
 *   | Case                                       | Behaviour |
 *   |--------------------------------------------|-----------|
 *   | exit_date IS NULL                          | accepted  |
 *   | end < exit_date (entirely before)          | accepted  |
 *   | start >= exit_date (entirely after)        | rejected  |
 *   | start < exit_date <= end (overlaps)        | rejected  |
 *
 * The rule does not validate the decorated field; the full period is
 * passed via the constructor and the rule acts as an "implies" check on
 * the `vehicleId` + `[start, end]` triplet. It is typically attached to
 * the `vehicleId` field or to the end date of the owning DTO.
 *
 * The vehicle is looked up on every evaluation · a single primary-key
 * query negligible compared to the rest of the validation chain.
 */
final class AvailableForPeriod implements ValidationRule
{
    public function __construct(
        private readonly int $vehicleId,
        private readonly CarbonImmutable $startDate,
        private readonly CarbonImmutable $endDate,
    ) {}

    /**
     * Validate the period against the vehicle's exit date and report a
     * French message via `$fail` when the period is not acceptable.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // ADR-0013 R3 · the lookup goes through the repository (instantiated
        // by `app()` because Spatie Data DTOs build the rule with `new`).
        $vehicle = app(VehicleReadRepositoryInterface::class)->findById($this->vehicleId);

        // A missing vehicle is caught by the upstream `exists:vehicles,id`
        // rule; nothing to validate here.
        if ($vehicle === null) {
            return;
        }

        if ($vehicle->exit_date === null) {
            return;
        }

        $exitDate = $vehicle->exit_date->toImmutable();
        $exitFr = $exitDate->format('d/m/Y');

        if ($this->endDate->lessThan($exitDate)) {
            return;
        }

        if ($this->startDate->greaterThanOrEqualTo($exitDate)) {
            $fail(sprintf(
                'Véhicule retiré depuis le %s. Aucune période n\'est autorisée à partir de cette date.',
                $exitFr,
            ));

            return;
        }

        $fail(sprintf(
            'Véhicule retiré le %s. La période ne peut pas dépasser cette date.',
            $exitFr,
        ));
    }
}
