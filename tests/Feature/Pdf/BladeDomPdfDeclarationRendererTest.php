<?php

declare(strict_types=1);

namespace Tests\Feature\Pdf;

use App\Data\User\FiscalDeclaration\DeclarationPreviewData;
use App\Enums\Contract\ContractType;
use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Enums\FiscalReviewDecision\RiskLevel;
use App\Fiscal\ValueObjects\AppliedDecisionEntry;
use App\Fiscal\ValueObjects\ContractSnapshotEntry;
use App\Fiscal\ValueObjects\DeclarationRenderContext;
use App\Fiscal\ValueObjects\FiscalDeclarationSnapshot;
use App\Services\Pdf\BladeDomPdfDeclarationRenderer;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre {@see BladeDomPdfDeclarationRenderer} (Phase 11 D5.4, refondu
 * D5.8.5 avec breakdown par contrat + clusters groupés visuellement).
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
    public function html_contient_les_lignes_du_breakdown_par_contrat_avec_periode_et_vehicule(): void
    {
        $html = $this->renderer->renderHtml($this->buildContext());

        // Section header
        self::assertStringContainsString('Détail chronologique par contrat', $html);
        // Période formatée FR
        self::assertStringContainsString('01/01/2024 → 19/07/2024', $html);
        // Type contrat
        self::assertStringContainsString('LLD', $html);
        // Véhicule + résumé fiscal
        self::assertStringContainsString('Peugeot 308 · AB-123-CD', $html);
        self::assertStringContainsString('M1 · WLTP 100 g · Euro 6', $html);
        // Jours
        self::assertStringContainsString('200', $html);
    }

    #[Test]
    public function html_groupe_les_contrats_d_un_meme_cluster_avec_header(): void
    {
        $html = $this->renderer->renderHtml($this->buildContextWithCluster());

        // Header de cluster visible (label + niveau + cumul)
        self::assertStringContainsString('Chaîne LCD', $html);
        self::assertStringContainsString('Risque moyen', $html);
        self::assertStringContainsString('2 contrats LCD', $html);
        self::assertStringContainsString('cumul 30', $html);
        // Décision conservée affichée dans le header de cluster
        self::assertStringContainsString('Conservé', $html);
        // Justification dans le header
        self::assertStringContainsString('Décision justifiée par le métier.', $html);
        // Marque cluster-row sur les lignes contrats
        self::assertStringContainsString('cluster-row', $html);
    }

    #[Test]
    public function html_marque_les_contrats_dont_la_decision_a_ete_reprise(): void
    {
        $html = $this->renderer->renderHtml($this->buildContextWithCluster());

        self::assertStringContainsString('décision reprise', $html);
    }

    #[Test]
    public function html_gere_gracieusement_le_cas_vide_sans_contrat(): void
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
            appliedDecisions: [],
            optOutContractIds: [],
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

    /**
     * Fixture avec 2 contrats LCD enchaînés sur le même véhicule formant
     * un cluster R-LCD-CHAIN décidé Conserved (avec justification +
     * `clusterDecisionRetainedFrom` posé pour matérialiser une décision
     * reprise par fingerprint).
     */
    private function buildContextWithCluster(): DeclarationRenderContext
    {
        $fingerprint = str_repeat('c', 64);

        $snapshot = new FiscalDeclarationSnapshot(
            companyId: 7,
            companyShortCode: 'ACM',
            companyLegalName: 'ACM SARL',
            fiscalYear: 2024,
            computedAt: CarbonImmutable::parse('2024-12-31 23:59:59'),
            co2DueTotal: 0.0,
            pollutantsDueTotal: 0.0,
            totalDue: 0.0,
            contractBreakdown: [
                new ContractSnapshotEntry(
                    contractId: 11,
                    contractReference: 'LCD-A',
                    contractType: ContractType::Lcd,
                    startDate: '2024-03-01',
                    endDate: '2024-03-15',
                    daysInYearAssigned: 15,
                    vehicleId: 42,
                    vehicleLabel: 'BMW Série 5 · EG-007-GG',
                    vehicleFiscalSummary: 'M1 · WLTP 130 g · Euro 6',
                    co2Due: 0.0,
                    pollutantsDue: 0.0,
                    totalDue: 0.0,
                    clusterFingerprint: $fingerprint,
                    clusterRiskCode: RiskCode::Chain,
                    clusterRiskLevel: RiskLevel::Moyen,
                    clusterDecision: ReviewDecisionType::Conserved,
                    clusterJustification: 'Décision justifiée par le métier.',
                    clusterDecisionRetainedFrom: 99,
                    isOptedOut: false,
                ),
                new ContractSnapshotEntry(
                    contractId: 12,
                    contractReference: 'LCD-B',
                    contractType: ContractType::Lcd,
                    startDate: '2024-04-01',
                    endDate: '2024-04-15',
                    daysInYearAssigned: 15,
                    vehicleId: 42,
                    vehicleLabel: 'BMW Série 5 · EG-007-GG',
                    vehicleFiscalSummary: 'M1 · WLTP 130 g · Euro 6',
                    co2Due: 0.0,
                    pollutantsDue: 0.0,
                    totalDue: 0.0,
                    clusterFingerprint: $fingerprint,
                    clusterRiskCode: RiskCode::Chain,
                    clusterRiskLevel: RiskLevel::Moyen,
                    clusterDecision: ReviewDecisionType::Conserved,
                    clusterJustification: 'Décision justifiée par le métier.',
                    clusterDecisionRetainedFrom: 99,
                    isOptedOut: false,
                ),
            ],
            appliedDecisions: [
                new AppliedDecisionEntry(
                    clusterFingerprint: $fingerprint,
                    riskCode: RiskCode::Chain,
                    decision: ReviewDecisionType::Conserved,
                    contractIds: [11, 12],
                    justification: 'Décision justifiée par le métier.',
                ),
            ],
            optOutContractIds: [],
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
            reference: 'DECL-ACM-2024-0002',
            generatedAt: CarbonImmutable::parse('2025-01-15 09:30:00'),
        );
    }
}
