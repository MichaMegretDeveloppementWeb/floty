<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\ValueObjects;

use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Fiscal\ValueObjects\AppliedDecisionEntry;
use App\Fiscal\ValueObjects\FiscalDeclarationSnapshot;
use App\Fiscal\ValueObjects\VehicleSnapshotEntry;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Couvre les VO {@see FiscalDeclarationSnapshot},
 * {@see VehicleSnapshotEntry} et {@see AppliedDecisionEntry} (Phase 11
 * D5.2).
 *
 * Vérifications :
 *   - construction nominale + accès aux champs
 *   - immutabilité (tentative de mutation = erreur de compilation/runtime)
 *   - structure du snapshot pour cas zéro (pas de véhicules ni décisions)
 */
final class FiscalDeclarationSnapshotTest extends TestCase
{
    #[Test]
    public function snapshot_se_construit_avec_tous_les_champs_renseignes(): void
    {
        $vehicleEntry = new VehicleSnapshotEntry(
            vehicleId: 42,
            vehicleLabel: 'Peugeot 308 · AB-123-CD',
            daysAssigned: 200,
            co2Due: 150.50,
            pollutantsDue: 80.25,
            totalDue: 230.75,
        );

        $decisionEntry = new AppliedDecisionEntry(
            clusterFingerprint: str_repeat('a', 64),
            riskCode: RiskCode::Chain,
            decision: ReviewDecisionType::Requalified,
            contractIds: [10, 11, 12],
            justification: 'Locations professionnelles répétées.',
        );

        $computedAt = CarbonImmutable::parse('2024-12-31 23:59:59');
        $snapshot = new FiscalDeclarationSnapshot(
            companyId: 7,
            companyShortCode: 'ACM',
            companyLegalName: 'ACM SARL',
            fiscalYear: 2024,
            computedAt: $computedAt,
            co2DueTotal: 150.50,
            pollutantsDueTotal: 80.25,
            totalDue: 230.75,
            vehicleBreakdown: [$vehicleEntry],
            appliedDecisions: [$decisionEntry],
            optOutContractIds: [10, 11, 12],
        );

        self::assertSame(7, $snapshot->companyId);
        self::assertSame('ACM', $snapshot->companyShortCode);
        self::assertSame('ACM SARL', $snapshot->companyLegalName);
        self::assertSame(2024, $snapshot->fiscalYear);
        self::assertTrue($snapshot->computedAt->equalTo($computedAt));
        self::assertSame(150.50, $snapshot->co2DueTotal);
        self::assertSame(80.25, $snapshot->pollutantsDueTotal);
        self::assertSame(230.75, $snapshot->totalDue);
        self::assertCount(1, $snapshot->vehicleBreakdown);
        self::assertSame($vehicleEntry, $snapshot->vehicleBreakdown[0]);
        self::assertCount(1, $snapshot->appliedDecisions);
        self::assertSame($decisionEntry, $snapshot->appliedDecisions[0]);
        self::assertSame([10, 11, 12], $snapshot->optOutContractIds);
    }

    #[Test]
    public function snapshot_zero_avec_aucun_vehicule_ni_decision_est_valide(): void
    {
        $snapshot = new FiscalDeclarationSnapshot(
            companyId: 7,
            companyShortCode: 'ACM',
            companyLegalName: 'ACM SARL',
            fiscalYear: 2024,
            computedAt: CarbonImmutable::now(),
            co2DueTotal: 0.0,
            pollutantsDueTotal: 0.0,
            totalDue: 0.0,
            vehicleBreakdown: [],
            appliedDecisions: [],
            optOutContractIds: [],
        );

        self::assertSame([], $snapshot->vehicleBreakdown);
        self::assertSame([], $snapshot->appliedDecisions);
        self::assertSame([], $snapshot->optOutContractIds);
        self::assertSame(0.0, $snapshot->totalDue);
    }

    #[Test]
    public function vehicle_entry_est_immuable_via_readonly(): void
    {
        $entry = new VehicleSnapshotEntry(
            vehicleId: 42,
            vehicleLabel: 'Test',
            daysAssigned: 100,
            co2Due: 50.0,
            pollutantsDue: 10.0,
            totalDue: 60.0,
        );

        $this->expectException(\Error::class);
        // PHP 8.4 : tentative de mutation sur readonly property → Error.
        // @phpstan-ignore-next-line property.readOnlyAssignNotInConstructor
        $entry->daysAssigned = 999;
    }

    #[Test]
    public function applied_decision_entry_supporte_justification_null(): void
    {
        $entry = new AppliedDecisionEntry(
            clusterFingerprint: str_repeat('b', 64),
            riskCode: RiskCode::ChainFort,
            decision: ReviewDecisionType::Conserved,
            contractIds: [1, 2],
            justification: null,
        );

        self::assertNull($entry->justification);
        self::assertSame(ReviewDecisionType::Conserved, $entry->decision);
        self::assertSame(RiskCode::ChainFort, $entry->riskCode);
    }
}
