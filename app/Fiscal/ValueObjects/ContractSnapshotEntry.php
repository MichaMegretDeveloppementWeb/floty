<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Enums\Contract\ContractType;
use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Enums\FiscalReviewDecision\RiskLevel;

/**
 * Ligne « contrat » d'un {@see FiscalDeclarationSnapshot} (Phase 11
 * D5.8). Remplace l'ancien `VehicleSnapshotEntry` : la déclaration
 * est désormais détaillée **par contrat** (vue chronologique), pas
 * par véhicule (vue agrégée opaque).
 *
 * **Pourquoi par contrat** : un véhicule utilisé 91 jours pour Y €
 * de taxe ne dit rien sur la répartition temporelle ni les motifs
 * (motorisation EV, opt-out LCD, cumul LCD). La vue par contrat
 * permet à l'utilisateur (et à l'administration en audit) de
 * comprendre **chaque ligne**, et matérialise les clusters LCD à
 * risque visuellement dans la liste elle-même (les contrats d'un
 * cluster partagent le même `clusterFingerprint`).
 *
 * **Tri** : le snapshot range les entrées par `(vehicleId,
 * startDate)` pour un groupage visuel naturel côté frontend. Les
 * contrats consécutifs d'un même cluster LCD sont donc adjacents,
 * permettant au composant frontend `<ClusterGroup>` de les
 * enrouler dans une « boîte » visuelle.
 *
 * **Montants** : `co2Due`, `pollutantsDue`, `totalDue` sont
 * arrondis au centime (HALF_UP). Leur somme sur l'ensemble des
 * entries égale {@see FiscalDeclarationSnapshot::$totalDue} à
 * l'arrondi près (R-2024-003 invariant d'arrondi unique par
 * redevable).
 *
 * **Répartition proportionnelle** : la taxe par contrat est
 * `(jours_contrat_année / jours_couple_année) × taxe_couple`,
 * cohérente avec le prorata journalier R-2024-002. Si tous les
 * contrats d'un couple sont LCD exonérés, taxe = 0 €.
 *
 * **Caractéristiques fiscales véhicule** : `vehicleFiscalSummary`
 * pré-formatée (ex. `M1 · WLTP 100 g · Euro 6`), utile à
 * l'administration en audit, plus parlant qu'un simple label.
 */
final readonly class ContractSnapshotEntry
{
    public function __construct(
        public int $contractId,
        public ?string $contractReference,
        public ContractType $contractType,
        /** ISO 8601 `Y-m-d`. */
        public string $startDate,
        /** ISO 8601 `Y-m-d`. */
        public string $endDate,
        /** Jours du contrat **dans l'année cible** (peut être < durée totale si à cheval). */
        public int $daysInYearAssigned,
        public int $vehicleId,
        public string $vehicleLabel,
        public string $vehicleFiscalSummary,
        public float $co2Due,
        public float $pollutantsDue,
        public float $totalDue,
        public ?string $clusterFingerprint,
        public ?RiskCode $clusterRiskCode,
        public ?RiskLevel $clusterRiskLevel,
        public ?ReviewDecisionType $clusterDecision,
        public ?string $clusterJustification,
        /**
         * ID de la déclaration prédécesseur d'où la décision a été
         * **reprise auto** par fingerprint matching (Phase 11 D5.8.2,
         * amélioration B audit). Null si :
         *  - aucune décision sur ce cluster, OU
         *  - décision prise pendant la session de revue courante.
         *
         * Permet au composant frontend `<ClusterGroup>` de matérialiser
         * un badge `🔁 Décision reprise de la version précédente` qui
         * distingue les décisions héritées des décisions à
         * trancher dans la session courante.
         */
        public ?int $clusterDecisionRetainedFrom,
        public bool $isOptedOut,
        /**
         * Phase 13 D5.10.W · raison d'exonération du contrat formatée
         * pour affichage utilisateur (PDF + UI). Null si le contrat
         * est taxé. Format · `« Exonéré R-XXXX-YYY · {nom court}
         * (CIBS L. {article}) »`. Calculé par
         * {@see App\Services\Fiscal\Declaration\DeclarationFiscalEngine}
         * d'après la qualification fiscale du contrat (LCD individuel
         * non opt-out → R-2024-021 ; autres cas à venir).
         */
        public ?string $exemptionReason,
    ) {}
}
