<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * Image immuable du calcul fiscal d'une déclaration pour un couple
 * `(company, year)` (Phase 11 D5.2, refondu D5.8 avec breakdown par
 * contrat au lieu de par véhicule).
 *
 * **Pourquoi** : le calcul d'une déclaration n'est pas un calcul fiscal
 * standard : il applique en plus les décisions humaines de revue
 * (« Requalified » sur certains clusters LCD). Le snapshot capture le
 * résultat tel qu'il a été calculé au moment précis de la génération,
 * avec la trace des décisions appliquées et des contrats opt-out
 * effectifs.
 *
 * **Refonte D5.8** : le breakdown est désormais **par contrat trié
 * chronologiquement** plutôt que par véhicule. Cette vue permet à
 * l'utilisateur de comprendre chaque ligne (date, durée, motif
 * d'exonération éventuel) et matérialise les chaînes LCD à risque
 * directement dans la liste via le champ `clusterFingerprint` partagé
 * par les contrats d'un même cluster. Cf. `ContractSnapshotEntry`.
 *
 * **Immuabilité stricte** : `final readonly` + propriétés promues + sous-VO
 * `final readonly`. Aucune mutation possible après construction.
 *
 * **Persistance** : sérialisé via `FiscalDeclarationSnapshotData` au
 * moment de `markAsGenerated()` (D5.5) pour garantir l'immuabilité
 * fiscale du PDF historique vs recalcul à la volée.
 */
final readonly class FiscalDeclarationSnapshot
{
    /**
     * @param  list<ContractSnapshotEntry>  $contractBreakdown  Détail par contrat trié chronologiquement (vehicleId, startDate)
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
        public array $contractBreakdown,
        public array $appliedDecisions,
        public array $optOutContractIds,
    ) {}
}
