<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Declaration;

use App\Actions\FiscalDeclaration\MarkDeclarationAsObsoleteAction;
use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Data\User\FiscalDeclaration\InvalidationReasonData;
use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\Contract;
use App\Models\FiscalDeclaration;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;

/**
 * Central detector of fiscal-declaration invalidation triggered by
 * mutations on fiscally-linked entities (ADR-0015 § D8).
 *
 * Single source of truth for the invalidating actions list. Observers
 * (Contract, VFC, Unavailability, Vehicle) delegate here; the detector
 * builds a typed `InvalidationReasonData` and runs
 * `MarkDeclarationAsObsoleteAction` for every impacted declaration.
 *
 * Scope · only `status=generated` declarations are marked obsolete.
 * Drafts (`draft`, `deferred`) recompute their perimeter live in the
 * review screen, so the obsolescence flag is meaningless for them and
 * caused UX issues (false "obsolete" label, blocked review access).
 *
 * Cross-year · a contract mutation invalidates declarations for every
 * year crossed by the contract before AND after the mutation.
 *
 * Short-circuit · if the entity has no fiscal impact (non-reductive
 * unavailability) the detector is called but flags nothing.
 */
final readonly class DeclarationInvalidationDetector
{
    public function __construct(
        private MarkDeclarationAsObsoleteAction $markAsObsolete,
        private ContractReadRepositoryInterface $contracts,
        private FiscalDeclarationReadRepositoryInterface $declarations,
    ) {}

    /**
     * Invalidates active `(company, year)` declarations impacted by a
     * `created`/`updated`/`deleted` contract mutation. For `updated`
     * also pass the previous bounds (via `getOriginal()`) so a range
     * change is fully covered.
     *
     * @param  list<string>  $fieldsChanged
     */
    public function flagForContract(
        Contract $contract,
        InvalidationReasonType $type,
        int $actorUserId,
        ?string $previousStartDate = null,
        ?string $previousEndDate = null,
        ?int $previousCompanyId = null,
        array $fieldsChanged = [],
    ): void {
        $years = $this->yearsForRange($contract->start_date->toDateString(), $contract->end_date->toDateString());

        if ($previousStartDate !== null && $previousEndDate !== null) {
            $years = array_unique(array_merge(
                $years,
                $this->yearsForRange($previousStartDate, $previousEndDate),
            ));
        }

        $companyIds = [$contract->company_id];
        if ($previousCompanyId !== null && $previousCompanyId !== $contract->company_id) {
            $companyIds[] = $previousCompanyId;
        }

        $entity = [
            'type' => 'contract',
            'id' => $contract->id,
            'label' => $this->labelContract($contract),
        ];

        foreach ($companyIds as $companyId) {
            $this->flagDeclarationsForCompanyYears(
                companyIds: [$companyId],
                years: $years,
                type: $type,
                entity: $entity,
                actorUserId: $actorUserId,
                fieldsChanged: $fieldsChanged,
            );
        }
    }

    /**
     * Invalidates declarations whose contracts use `$vfc->vehicle_id`
     * within the year.
     */
    public function flagForVfcMutation(
        VehicleFiscalCharacteristics $vfc,
        InvalidationReasonType $type,
        int $actorUserId,
    ): void {
        $tuples = $this->contracts->findContractDateRangesForVehicle($vfc->vehicle_id);

        $entity = [
            'type' => 'vehicle_fiscal_characteristics',
            'id' => $vfc->id,
            'label' => sprintf(
                'VFC #%d · véhicule %d · effective %s',
                $vfc->id,
                $vfc->vehicle_id,
                $vfc->effective_from->format('d/m/Y'),
            ),
        ];

        foreach ($tuples as $tuple) {
            $years = $this->yearsForRange((string) $tuple->start_date, (string) $tuple->end_date);
            $this->flagDeclarationsForCompanyYears(
                companyIds: [(int) $tuple->company_id],
                years: $years,
                type: $type,
                entity: $entity,
                actorUserId: $actorUserId,
                fieldsChanged: [],
            );
        }
    }

    /**
     * Invalidates on a Vehicle mutation (currently only triggered on
     * `exit_date` change via {@see App\Observers\VehicleObserver}). The
     * field closes contracts past the exit date, so the taxable
     * perimeter shifts even when contracts themselves are unchanged.
     *
     * @param  list<string>  $fieldsChanged
     */
    public function flagForVehicle(
        Vehicle $vehicle,
        InvalidationReasonType $type,
        int $actorUserId,
        array $fieldsChanged = [],
    ): void {
        $tuples = $this->contracts->findContractDateRangesForVehicle($vehicle->id);

        $entity = [
            'type' => 'vehicle',
            'id' => $vehicle->id,
            'label' => sprintf(
                '%s · %s %s',
                $vehicle->license_plate,
                $vehicle->brand,
                $vehicle->model,
            ),
        ];

        foreach ($tuples as $tuple) {
            $years = $this->yearsForRange((string) $tuple->start_date, (string) $tuple->end_date);
            $this->flagDeclarationsForCompanyYears(
                companyIds: [(int) $tuple->company_id],
                years: $years,
                type: $type,
                entity: $entity,
                actorUserId: $actorUserId,
                fieldsChanged: $fieldsChanged,
            );
        }
    }

    /**
     * Invalidates on an unavailability mutation. Short-circuits when
     * neither the current nor the previous state had fiscal impact
     * (`has_fiscal_impact` is the denormalised mirror of
     * {@see App\Enums\UnavailabilityType::isFiscallyReductive()}).
     */
    public function flagForUnavailability(
        Unavailability $unavailability,
        InvalidationReasonType $type,
        int $actorUserId,
        ?bool $previousHasFiscalImpact = null,
    ): void {
        if (! $unavailability->has_fiscal_impact && $previousHasFiscalImpact !== true) {
            return;
        }

        $startCarbon = $unavailability->start_date;
        $endCarbon = $unavailability->end_date ?? Carbon::now()->endOfYear();
        $years = $this->yearsForRange($startCarbon->toDateString(), $endCarbon->toDateString());

        $tuples = $this->contracts->findContractDateRangesForVehicle($unavailability->vehicle_id);

        $entity = [
            'type' => 'unavailability',
            'id' => $unavailability->id,
            'label' => sprintf(
                'Indispo #%d · véhicule %d · %s → %s',
                $unavailability->id,
                $unavailability->vehicle_id,
                $startCarbon->format('d/m/Y'),
                $endCarbon->format('d/m/Y'),
            ),
        ];

        foreach ($tuples as $tuple) {
            $contractYears = $this->yearsForRange((string) $tuple->start_date, (string) $tuple->end_date);
            $intersection = array_values(array_intersect($years, $contractYears));
            if ($intersection === []) {
                continue;
            }
            $this->flagDeclarationsForCompanyYears(
                companyIds: [(int) $tuple->company_id],
                years: $intersection,
                type: $type,
                entity: $entity,
                actorUserId: $actorUserId,
                fieldsChanged: [],
            );
        }
    }

    /**
     * @param  list<int>  $companyIds
     * @param  list<int>  $years
     * @param  array{type: string, id: int, label: string}  $entity
     * @param  list<string>  $fieldsChanged
     */
    private function flagDeclarationsForCompanyYears(
        array $companyIds,
        array $years,
        InvalidationReasonType $type,
        array $entity,
        int $actorUserId,
        array $fieldsChanged,
    ): void {
        if ($years === [] || $companyIds === []) {
            return;
        }

        // Only `generated` declarations are flagged. Drafts compute
        // their perimeter live in Review; the obsolescence flag is
        // meaningless for them. Multiple obsolescence reasons may
        // accumulate (ADR-0015 § D8), so no `is_obsolete` filter.
        $declarations = $this->declarations->findGeneratedForCompanyYears($companyIds, $years);

        foreach ($declarations as $declaration) {
            $reason = new InvalidationReasonData(
                type: $type,
                occurredAt: Carbon::now()->toIso8601String(),
                actorUserId: $actorUserId,
                entity: $entity,
                fieldsChanged: $fieldsChanged,
            );

            $this->markAsObsolete->execute($declaration->id, $reason);
            $this->pushToast($declaration);
        }
    }

    /**
     * Cross-surface warning toast through the Laravel `toast-warning`
     * flash channel, consumed by `useFlashToasts` on the next Inertia
     * response. Laravel flash holds a single message per channel, so
     * only the last invalidation of a given request surfaces · acceptable
     * for V1 (marginal case).
     */
    private function pushToast(FiscalDeclaration $declaration): void
    {
        $shortCode = $declaration->company->short_code ?? sprintf('Entreprise %d', $declaration->company_id);
        Session::flash(
            'toast-warning',
            sprintf(
                'Déclaration %s %d invalidée. Régénérer pour reprendre le calcul fiscal.',
                $shortCode,
                $declaration->fiscal_year,
            ),
        );
    }

    /**
     * @return list<int>
     */
    private function yearsForRange(string $start, string $end): array
    {
        $startCarbon = CarbonImmutable::parse($start);
        $endCarbon = CarbonImmutable::parse($end);

        if ($startCarbon->isAfter($endCarbon)) {
            return [];
        }

        $years = [];
        for ($y = $startCarbon->year; $y <= $endCarbon->year; $y++) {
            $years[] = $y;
        }

        return $years;
    }

    private function labelContract(Contract $contract): string
    {
        return sprintf(
            'Contrat #%d · véhicule %d · %s → %s',
            $contract->id,
            $contract->vehicle_id,
            $contract->start_date->format('d/m/Y'),
            $contract->end_date->format('d/m/Y'),
        );
    }
}
