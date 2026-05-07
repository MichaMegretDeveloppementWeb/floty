<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\Contract;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Fiscal\Declaration\DeclarationInvalidationDetector;
use App\Services\Invoice\InvoiceDivergenceFlagger;
use Illuminate\Support\Facades\Auth;

/**
 * Invalide le cache des bornes d'années sélectionnables (chantier η,
 * Phase 0.2) **et** marque divergentes les factures impactées (T6 /
 * Phase 14.R) à chaque mutation d'un {@see Contract}.
 *
 * **Pourquoi un Observer plutôt qu'un Event Listener ou une invalidation
 * dans les Actions** :
 *   - **Couverture totale** : capte aussi les mutations passant par
 *     factories, seeders, console (`tinker`), tests, sans que ces
 *     chemins aient besoin de connaître les invalidations.
 *   - **Single source of truth** : 1 seul fichier porte la responsabilité
 *     « toute mutation de Contract = invalidations associées ».
 *   - **Découplage** : les Actions Contract restent agnostiques des
 *     consommateurs (séparation des préoccupations ADR-0013).
 *
 * **Branchement** : déclaré sur le modèle {@see Contract} via l'attribut
 * `#[ObservedBy([ContractObserver::class])]` (Laravel 11+, cohérent avec
 * `#[Fillable]` déjà utilisé sur ce modèle). Pas de bind dans
 * `AppServiceProvider::boot()`.
 *
 * **Hooks couverts** : created / updated / deleted / restored /
 * forceDeleted. Couvre l'intégralité du cycle de vie possible (incluant
 * la pose et le retrait du `deleted_at` qui modifient le périmètre des
 * contrats actifs vu par {@see AvailableYearsResolver}).
 *
 * **Invalidation factures (T6)** : seules les mutations modifiant le
 * périmètre facturable déclenchent un flag `is_divergent = true`.
 * Les changements purement annotatifs (`notes`, `contract_reference`)
 * sont skippés pour éviter les faux positifs.
 */
final class ContractObserver
{
    private const array IMPACTING_FIELDS = [
        'start_date',
        'end_date',
        'company_id',
        'vehicle_id',
    ];

    public function __construct(
        private readonly AvailableYearsResolver $resolver,
        private readonly InvoiceDivergenceFlagger $flagger,
        private readonly DeclarationInvalidationDetector $declarationDetector,
    ) {}

    public function created(Contract $contract): void
    {
        $this->resolver->forgetCache();

        $this->flagger->flagForContractRange(
            $contract->company_id,
            $contract->start_date->toDateString(),
            $contract->end_date->toDateString(),
        );

        $this->declarationDetector->flagForContract(
            $contract,
            InvalidationReasonType::ContractCreated,
            $this->actorUserId(),
        );
    }

    public function updated(Contract $contract): void
    {
        $this->resolver->forgetCache();

        if (! $contract->wasChanged(self::IMPACTING_FIELDS)) {
            return;
        }

        $oldCompanyId = (int) ($contract->getOriginal('company_id') ?? $contract->company_id);
        $newCompanyId = (int) $contract->company_id;
        $oldStart = $this->dateToString($contract->getOriginal('start_date'));
        $oldEnd = $this->dateToString($contract->getOriginal('end_date'));
        $newStart = $contract->start_date->toDateString();
        $newEnd = $contract->end_date->toDateString();

        if ($oldCompanyId === $newCompanyId) {
            $this->flagger->flagForContractRange(
                $newCompanyId,
                $newStart,
                $newEnd,
                $oldStart,
                $oldEnd,
            );
        } else {
            // Changement de company : flag les deux périmètres séparément
            // (l'ancienne entreprise n'est plus liée mais ses factures
            // doivent quand même refléter la perte du contrat).
            $this->flagger->flagForContractRange($oldCompanyId, $oldStart, $oldEnd);
            $this->flagger->flagForContractRange($newCompanyId, $newStart, $newEnd);
        }

        $this->declarationDetector->flagForContract(
            $contract,
            InvalidationReasonType::ContractUpdated,
            $this->actorUserId(),
            previousStartDate: $oldStart,
            previousEndDate: $oldEnd,
            previousCompanyId: $oldCompanyId !== $newCompanyId ? $oldCompanyId : null,
            fieldsChanged: array_values(array_intersect(
                self::IMPACTING_FIELDS,
                array_keys($contract->getChanges()),
            )),
        );
    }

    public function deleted(Contract $contract): void
    {
        $this->resolver->forgetCache();

        $this->flagger->flagForContractRange(
            $contract->company_id,
            $contract->start_date->toDateString(),
            $contract->end_date->toDateString(),
        );

        $this->declarationDetector->flagForContract(
            $contract,
            InvalidationReasonType::ContractDeleted,
            $this->actorUserId(),
        );
    }

    public function restored(Contract $contract): void
    {
        $this->resolver->forgetCache();

        $this->flagger->flagForContractRange(
            $contract->company_id,
            $contract->start_date->toDateString(),
            $contract->end_date->toDateString(),
        );

        // Restoration = ré-ajout d'un contrat soft-deleted = mêmes
        // implications fiscales qu'une création.
        $this->declarationDetector->flagForContract(
            $contract,
            InvalidationReasonType::ContractCreated,
            $this->actorUserId(),
        );
    }

    public function forceDeleted(Contract $contract): void
    {
        $this->resolver->forgetCache();

        $this->flagger->flagForContractRange(
            $contract->company_id,
            $contract->start_date->toDateString(),
            $contract->end_date->toDateString(),
        );

        $this->declarationDetector->flagForContract(
            $contract,
            InvalidationReasonType::ContractDeleted,
            $this->actorUserId(),
        );
    }

    /**
     * Identifie l'utilisateur acteur de la mutation pour la traçabilité
     * des motifs `obsolete_reasons`. `0` si contexte sans auth (CLI,
     * factories, seeders, tests sans `actingAs()`).
     */
    private function actorUserId(): int
    {
        return (int) (Auth::id() ?? 0);
    }

    /**
     * `getOriginal` peut retourner un Carbon (cast) ou une string (avant
     * cast). Normalise en `Y-m-d`.
     */
    private function dateToString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }
}
