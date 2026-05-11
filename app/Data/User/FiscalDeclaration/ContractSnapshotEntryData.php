<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Enums\Contract\ContractType;
use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Enums\FiscalReviewDecision\RiskLevel;
use App\Fiscal\ValueObjects\ContractSnapshotEntry;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Représentation frontend d'une ligne « contrat » d'un snapshot fiscal
 * (Phase 11 D5.8). Miroir DTO du VO domaine
 * {@see ContractSnapshotEntry}.
 *
 * Affiché par le composant
 * {@see resources/js/Components/Domain/Declaration/DeclarationContractList.vue}
 * qui groupe les rows consécutives partageant le même
 * `clusterFingerprint` dans des « boîtes » visuelles permettant à
 * l'utilisateur de comprendre la chaîne LCD à risque dans la liste
 * elle-même, sans renvoi vers une section dédiée.
 */
#[TypeScript]
final class ContractSnapshotEntryData extends Data
{
    public function __construct(
        public int $contractId,
        public ?string $contractReference,
        public ContractType $contractType,
        public string $startDate,
        public string $endDate,
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
        public bool $isOptedOut,
    ) {}

    public static function fromValueObject(ContractSnapshotEntry $vo): self
    {
        return new self(
            contractId: $vo->contractId,
            contractReference: $vo->contractReference,
            contractType: $vo->contractType,
            startDate: $vo->startDate,
            endDate: $vo->endDate,
            daysInYearAssigned: $vo->daysInYearAssigned,
            vehicleId: $vo->vehicleId,
            vehicleLabel: $vo->vehicleLabel,
            vehicleFiscalSummary: $vo->vehicleFiscalSummary,
            co2Due: $vo->co2Due,
            pollutantsDue: $vo->pollutantsDue,
            totalDue: $vo->totalDue,
            clusterFingerprint: $vo->clusterFingerprint,
            clusterRiskCode: $vo->clusterRiskCode,
            clusterRiskLevel: $vo->clusterRiskLevel,
            clusterDecision: $vo->clusterDecision,
            clusterJustification: $vo->clusterJustification,
            isOptedOut: $vo->isOptedOut,
        );
    }
}
