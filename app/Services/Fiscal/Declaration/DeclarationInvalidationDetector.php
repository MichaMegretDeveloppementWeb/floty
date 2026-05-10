<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Declaration;

use App\Actions\FiscalDeclaration\MarkDeclarationAsObsoleteAction;
use App\Data\User\FiscalDeclaration\InvalidationReasonData;
use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\Contract;
use App\Models\FiscalDeclaration;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Invoice\InvoiceDivergenceFlagger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * Service centralisé d'invalidation des déclarations fiscales suite
 * à une mutation d'entité fiscalement liée (Phase 11 D3, ADR-0015 § D8).
 *
 * Source unique de vérité de la liste des actions invalidantes /
 * non-invalidantes. Les Observers (Contract, VFC, Unavailability)
 * délèguent à ce service ; le service construit un
 * `InvalidationReasonData` typé et appelle
 * `MarkDeclarationAsObsoleteAction` pour chaque déclaration impactée.
 *
 * Pattern direct {@see InvoiceDivergenceFlagger}.
 *
 * **Cross-année** : une mutation contrat invalide les déclarations de
 * toutes les années croisées par le contrat (avant ET après la
 * mutation). Cf. décision D3.2 du plan D3.
 *
 * **Court-circuit** : si l'entité n'a pas d'impact fiscal (indispo
 * non réductrice), le service est appelé mais ne flag rien.
 */
final readonly class DeclarationInvalidationDetector
{
    public function __construct(
        private MarkDeclarationAsObsoleteAction $markAsObsolete,
    ) {}

    /**
     * Invalide les déclarations actives `(company, year)` impactées
     * par une mutation `created`/`updated`/`deleted` de contrat.
     *
     * Pour `updated` : passer aussi les bornes ancienne (via
     * `getOriginal()`) pour couvrir les changements de plage.
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
     * Invalide pour une mutation de VFC : les déclarations dont au
     * moins un contrat utilise `$vfc->vehicle_id` sur l'année.
     */
    public function flagForVfcMutation(
        VehicleFiscalCharacteristics $vfc,
        InvalidationReasonType $type,
        int $actorUserId,
    ): void {
        $tuples = DB::table('contracts')
            ->where('vehicle_id', $vfc->vehicle_id)
            ->whereNull('deleted_at')
            ->select(['company_id', 'start_date', 'end_date'])
            ->get();

        $entity = [
            'type' => 'vehicle_fiscal_characteristics',
            'id' => $vfc->id,
            'label' => sprintf(
                'VFC #%d · véhicule %d · effective %s',
                $vfc->id,
                $vfc->vehicle_id,
                $vfc->effective_from?->toDateString() ?? '?',
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
     * Invalide pour une mutation de Vehicle (Phase 11 D5.7.8 audit
     * pré-livraison) : actuellement uniquement déclenché par
     * {@see App\Observers\VehicleObserver::updated} sur changement
     * d'`exit_date`. Le champ clôture les contrats au-delà de la date
     * de sortie, donc le périmètre taxable change même si les
     * contrats eux-mêmes n'ont pas été modifiés.
     *
     * Périmètre : les déclarations dont au moins un contrat utilise
     * ce véhicule sur l'année.
     *
     * @param  list<string>  $fieldsChanged
     */
    public function flagForVehicle(
        Vehicle $vehicle,
        InvalidationReasonType $type,
        int $actorUserId,
        array $fieldsChanged = [],
    ): void {
        $tuples = DB::table('contracts')
            ->where('vehicle_id', $vehicle->id)
            ->whereNull('deleted_at')
            ->select(['company_id', 'start_date', 'end_date'])
            ->get();

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
     * Invalide pour une mutation d'indispo. Court-circuite si
     * l'indispo n'a aucun impact fiscal (champ dénormalisé
     * `has_fiscal_impact` cohérent avec
     * `UnavailabilityType::isFiscallyReductive()` via CHECK SQL).
     */
    public function flagForUnavailability(
        Unavailability $unavailability,
        InvalidationReasonType $type,
        int $actorUserId,
        ?bool $previousHasFiscalImpact = null,
    ): void {
        // Court-circuit si ni l'état actuel ni l'ancien n'avaient
        // d'impact fiscal (indispo « notes only » par exemple).
        if (! $unavailability->has_fiscal_impact && $previousHasFiscalImpact !== true) {
            return;
        }

        // Périmètre : les déclarations dont au moins un contrat
        // utilise ce véhicule sur l'année croisée par l'indispo.
        $start = $unavailability->start_date->toDateString();
        $end = ($unavailability->end_date ?? Carbon::now()->endOfYear())->toDateString();
        $years = $this->yearsForRange($start, $end);

        $tuples = DB::table('contracts')
            ->where('vehicle_id', $unavailability->vehicle_id)
            ->whereNull('deleted_at')
            ->select(['company_id', 'start_date', 'end_date'])
            ->get();

        $entity = [
            'type' => 'unavailability',
            'id' => $unavailability->id,
            'label' => sprintf(
                'Indispo #%d · véhicule %d · %s → %s',
                $unavailability->id,
                $unavailability->vehicle_id,
                $start,
                $end,
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

        // Une déclaration est « impactable » si :
        //   - statut `generated` (normal post-génération), OU
        //   - statut `draft`/`deferred` (impacte la prochaine génération)
        //   - et n'est pas soft-deleted
        // L'obsolescence multiple est autorisée par doctrine D8 (motifs
        // s'empilent), pas de filtre sur is_obsolete.
        $declarations = FiscalDeclaration::query()
            ->with('company:id,short_code,legal_name')
            ->whereIn('company_id', $companyIds)
            ->whereIn('fiscal_year', $years)
            ->get();

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
     * Push toast warning cross-surface (Phase 11 D4) sur le canal flash
     * Laravel `toast-warning`. Consommé automatiquement par
     * `useFlashToasts` (UserLayout) au prochain Inertia response.
     *
     * Limitation : Laravel session ne stocke qu'un message par canal.
     * Si plusieurs déclarations sont invalidées dans une même requête,
     * seule la dernière est affichée. Acceptable en V1 (cas marginal).
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
            $contract->start_date->toDateString(),
            $contract->end_date->toDateString(),
        );
    }
}
