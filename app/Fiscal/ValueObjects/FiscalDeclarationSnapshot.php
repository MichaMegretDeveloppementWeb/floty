<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * Image immuable du calcul fiscal d'une déclaration pour un couple
 * `(company, year)` (Phase 11 D5.2, ADR-0015 § 5.1 rev. 1.1).
 *
 * **Pourquoi** : le calcul d'une déclaration n'est pas un calcul fiscal
 * standard - il applique en plus les décisions humaines de revue
 * (« Requalified » sur certains clusters LCD). Le snapshot capture le
 * résultat tel qu'il a été calculé au moment précis de la génération,
 * avec la trace des décisions appliquées et des contrats opt-out
 * effectifs. Il sera consommé par le rendu PDF (D5.4) et persisté
 * sérialisé en BDD (D5.5) pour l'audit historique.
 *
 * **Immuabilité stricte** : `final readonly` + propriétés promues + sous-VO
 * `final readonly`. Aucune mutation possible après construction.
 *
 * **Hors scope D5.2** : pas de sérialisation JSON (D5.5), pas de DTO
 * frontend (D5.6, dérivé via `Spatie\LaravelData`).
 */
final readonly class FiscalDeclarationSnapshot
{
    /**
     * @param  list<VehicleSnapshotEntry>  $vehicleBreakdown  Détail par véhicule utilisé sur l'année
     * @param  list<AppliedDecisionEntry>  $appliedDecisions  Décisions de revue persistées matchées sur les clusters re-détectés
     * @param  list<int>  $optOutContractIds  IDs des contrats requalifiés effectivement appliqués (collation Requalified)
     */
    public function __construct(
        public int $companyId,
        public string $companyShortCode,
        public string $companyLegalName,
        public int $fiscalYear,
        public CarbonImmutable $computedAt,
        public float $co2DueTotal,
        public float $pollutantsDueTotal,
        public float $totalDue,
        public array $vehicleBreakdown,
        public array $appliedDecisions,
        public array $optOutContractIds,
    ) {}
}
