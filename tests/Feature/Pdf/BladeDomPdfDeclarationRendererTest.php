<?php

declare(strict_types=1);

namespace Tests\Feature\Pdf;

use App\Data\User\FiscalDeclaration\DeclarationPreviewData;
use App\Enums\Contract\ContractType;
use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Fiscal\ValueObjects\AppliedDecisionEntry;
use App\Fiscal\ValueObjects\ContractSnapshotEntry;
use App\Fiscal\ValueObjects\DeclarationRenderContext;
use App\Fiscal\ValueObjects\FiscalDeclarationSnapshot;
use App\Services\Pdf\BladeDomPdfDeclarationRenderer;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre {@see BladeDomPdfDeclarationRenderer} (Phase 11 D5.4).
 *
 * Stratégie : tests d'isolation pure du renderer avec un
 * `DeclarationRenderContext` fabriqué en mémoire (pas de BDD).
 *
 * Approche tests :
 *   - 1 test smoke sur `render()` : binary commence par `%PDF-`, taille
 *     raisonnable.
 *   - Tests de contenu via `renderHtml()` : on vérifie la présence de
 *     chaînes attendues dans le HTML intermédiaire, plus simple et plus
 *     robuste que parser le PDF binaire.
 *
 * Pas de `RefreshDatabase` : on injecte un context fabriqué sans aucune
 * dépendance DB.
 */
final class BladeDomPdfDeclarationRendererTest extends TestCase
{
    private BladeDomPdfDeclarationRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = $this->app->make(BladeDomPdfDeclarationRenderer::class);
    }

    #[Test]
    public function render_produit_un_pdf_binary_valide(): void
    {
        $binary = $this->renderer->render($this->buildContext());

        self::assertStringStartsWith('%PDF-', $binary);
        self::assertGreaterThan(1024, strlen($binary), 'Le PDF doit avoir une taille raisonnable.');
    }

    #[Test]
    public function html_contient_la_reference_dans_l_entete(): void
    {
        $html = $this->renderer->renderHtml($this->buildContext());

        self::assertStringContainsString('DECL-ACM-2024-0001', $html);
    }

    #[Test]
    public function html_contient_les_totaux_fiscaux_formates_en_euros(): void
    {
        $html = $this->renderer->renderHtml($this->buildContext());

        // Format FR : `1 234,56 €` avec espace fine U+202F.
        self::assertStringContainsString("150,50\u{202F}€", $html);
        self::assertStringContainsString("80,25\u{202F}€", $html);
        self::assertStringContainsString("230,75\u{202F}€", $html);
    }

    #[Test]
    public function html_contient_les_lignes_du_breakdown_par_vehicule(): void
    {
        $html = $this->renderer->renderHtml($this->buildContext());

        self::assertStringContainsString('Peugeot 308 · AB-123-CD', $html);
        self::assertStringContainsString('200', $html); // daysAssigned
    }

    #[Test]
    public function html_contient_les_decisions_appliquees_avec_justification(): void
    {
        $html = $this->renderer->renderHtml($this->buildContext());

        self::assertStringContainsString('Locations professionnelles répétées.', $html);
        self::assertStringContainsString('Requalifié', $html);
        // Les 12 premiers chars du fingerprint exemple (caractère 'a' répété).
        self::assertStringContainsString('aaaaaaaaaaaa', $html);
    }

    #[Test]
    public function html_gere_gracieusement_les_cas_vides_sans_vehicule_ni_decision(): void
    {
        $emptySnapshot = new FiscalDeclarationSnapshot(
            companyId: 7,
            companyShortCode: 'ACM',
            companyLegalName: 'ACM SARL',
            fiscalYear: 2024,
            computedAt: CarbonImmutable::parse('2024-12-31 23:59:59'),
            co2DueTotal: 0.0,
            pollutantsDueTotal: 0.0,
            totalDue: 0.0,
            contractBreakdown: [],
            appliedDecisions: [],
            optOutContractIds: [],
        );
        $emptyPreview = new DeclarationPreviewData(
            companyId: 7,
            companyShortCode: 'ACM',
            companyLegalName: 'ACM SARL',
            fiscalYear: 2024,
            clusters: [],
            pendingClustersCount: 0,
            canGenerate: true,
            declaration: null,
        );
        $context = new DeclarationRenderContext(
            preview: $emptyPreview,
            snapshot: $emptySnapshot,
            reference: 'DECL-ACM-2024-0001',
            generatedAt: CarbonImmutable::now(),
        );

        $html = $this->renderer->renderHtml($context);

        self::assertStringContainsString('Aucun véhicule attribué', $html);
        self::assertStringContainsString('Aucune chaîne LCD', $html);
    }

    #[Test]
    public function html_contient_le_sceau_de_generation_avec_la_reference(): void
    {
        $html = $this->renderer->renderHtml($this->buildContext());

        self::assertStringContainsString('Sceau de génération', $html);
        self::assertStringContainsString('Annexe documentaire', $html);
    }

    private function buildContext(): DeclarationRenderContext
    {
        $snapshot = new FiscalDeclarationSnapshot(
            companyId: 7,
            companyShortCode: 'ACM',
            companyLegalName: 'ACM SARL',
            fiscalYear: 2024,
            computedAt: CarbonImmutable::parse('2024-12-31 23:59:59'),
            co2DueTotal: 150.50,
            pollutantsDueTotal: 80.25,
            totalDue: 230.75,
            contractBreakdown: [
                new ContractSnapshotEntry(
                    contractId: 10,
                    contractReference: 'REF-001',
                    contractType: ContractType::Lld,
                    startDate: '2024-01-01',
                    endDate: '2024-07-19',
                    daysInYearAssigned: 200,
                    vehicleId: 42,
                    vehicleLabel: 'Peugeot 308 · AB-123-CD',
                    vehicleFiscalSummary: 'M1 · WLTP 100 g · Euro 6',
                    co2Due: 150.50,
                    pollutantsDue: 80.25,
                    totalDue: 230.75,
                    clusterFingerprint: null,
                    clusterRiskCode: null,
                    clusterRiskLevel: null,
                    clusterDecision: null,
                    clusterJustification: null,
                    clusterDecisionRetainedFrom: null,
                    isOptedOut: false,
                ),
            ],
            appliedDecisions: [
                new AppliedDecisionEntry(
                    clusterFingerprint: str_repeat('a', 64),
                    riskCode: RiskCode::Chain,
                    decision: ReviewDecisionType::Requalified,
                    contractIds: [10, 11, 12],
                    justification: 'Locations professionnelles répétées.',
                ),
            ],
            optOutContractIds: [10, 11, 12],
        );

        $preview = new DeclarationPreviewData(
            companyId: 7,
            companyShortCode: 'ACM',
            companyLegalName: 'ACM SARL',
            fiscalYear: 2024,
            clusters: [],
            pendingClustersCount: 0,
            canGenerate: true,
            declaration: null,
        );

        return new DeclarationRenderContext(
            preview: $preview,
            snapshot: $snapshot,
            reference: 'DECL-ACM-2024-0001',
            generatedAt: CarbonImmutable::parse('2025-01-15 09:30:00'),
        );
    }
}
