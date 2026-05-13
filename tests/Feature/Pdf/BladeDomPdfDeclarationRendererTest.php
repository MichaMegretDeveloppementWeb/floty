<?php

declare(strict_types=1);

namespace Tests\Feature\Pdf;

use App\Data\User\FiscalDeclaration\DeclarationPreviewData;
use App\Data\User\FiscalDeclaration\FiscalDeclarationSnapshotData;
use App\Enums\Contract\ContractType;
use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Enums\FiscalReviewDecision\RiskLevel;
use App\Fiscal\ValueObjects\AppliedDecisionEntry;
use App\Fiscal\ValueObjects\ContractSnapshotEntry;
use App\Fiscal\ValueObjects\DeclarationRenderContext;
use App\Fiscal\ValueObjects\FiscalDeclarationSnapshot;
use App\Services\Fiscal\SnapshotHashCalculator;
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
        // Véhicule + résumé fiscal
        self::assertStringContainsString('Peugeot 308 · AB-123-CD', $html);
        self::assertStringContainsString('M1 · WLTP 100 g · Euro 6', $html);
        // Jours
        self::assertStringContainsString('200', $html);
    }

    #[Test]
    public function html_ne_contient_pas_de_colonne_type_lcd_lld(): void
    {
        // Phase 13 D5.10.W · la colonne « Type » (LCD/LLD) est retirée
        // du PDF officiel · elle ajoutait du bruit sans valeur pour
        // l'administration qui lit uniquement la période et le montant.
        $html = $this->renderer->renderHtml($this->buildContext());

        self::assertStringNotContainsString('<th>Type</th>', $html);
    }

    #[Test]
    public function html_affiche_la_mention_exoneration_pour_un_contrat_exonere(): void
    {
        // Phase 13 D5.10.W · les contrats LCD individuels non opt-out
        // sont exonérés au titre de R-2024-021 et doivent porter une
        // mention compacte sous le véhicule indiquant le motif et
        // l'article CIBS associé, pour permettre à l'administration
        // d'auditer la déclaration sans page d'annexes.
        $html = $this->renderer->renderHtml($this->buildContextWithExemption());

        self::assertStringContainsString(
            'Exonéré R-2024-021 · LCD courte durée (CIBS L. 421-129)',
            $html,
        );
    }

    #[Test]
    public function html_ne_contient_aucune_annotation_interne_de_revue(): void
    {
        // Phase 13 D5.10.J · le PDF officiel ne doit plus exposer les
        // mentions de revue interne (cluster headers, niveaux de risque,
        // décisions, justifications, marques de décision reprise). Seuls
        // les contrats avec leur traitement fiscal final apparaissent.
        $html = $this->renderer->renderHtml($this->buildContextWithCluster());

        self::assertStringNotContainsString('Chaîne LCD', $html);
        self::assertStringNotContainsString('Risque moyen', $html);
        self::assertStringNotContainsString('contrats LCD', $html);
        self::assertStringNotContainsString('cumul', $html);
        self::assertStringNotContainsString('Conservé', $html);
        self::assertStringNotContainsString('Décision justifiée par le métier.', $html);
        self::assertStringNotContainsString('cluster-row', $html);
        self::assertStringNotContainsString('décision reprise', $html);
    }

    #[Test]
    public function html_ne_contient_pas_le_bloc_mentions_legales(): void
    {
        // Phase 13 D5.10.J · le bloc « mentions légales » pédagogique
        // (CIBS / BOFiP / R-2024-021) est retiré du document officiel.
        // Phase 13 D5.10.W · le motif R-2024-021 peut désormais
        // apparaître localement sous un contrat exonéré (mention
        // courte) · on garde une fixture sans exonération pour
        // garantir que l'ancien bloc pédagogique n'a pas resurgi.
        $html = $this->renderer->renderHtml($this->buildContext());

        self::assertStringNotContainsString('Mentions légales', $html);
        self::assertStringNotContainsString('R-2024-021', $html);
    }

    #[Test]
    public function html_ne_contient_pas_de_tag_annexe_documentaire(): void
    {
        // Phase 13 D5.10.J · plus de tag « Annexe documentaire » dans
        // l'entête · le document est officiel.
        $html = $this->renderer->renderHtml($this->buildContext());

        self::assertStringNotContainsString('Annexe documentaire', $html);
        self::assertStringNotContainsString('doc-tag', $html);
    }

    #[Test]
    public function html_expose_le_sha256_du_snapshot_dans_le_sceau(): void
    {
        // Phase 13 D5.10.J · empreinte fiscale déterministe imprimée
        // dans le sceau de génération · doit matcher le hash calculé
        // côté Show pour permettre la vérification d'intégrité.
        $context = $this->buildContext();
        $expectedHash = SnapshotHashCalculator::compute(
            FiscalDeclarationSnapshotData::fromValueObject($context->snapshot)->toArray(),
        );
        $html = $this->renderer->renderHtml($context);

        self::assertStringContainsString('SHA-256', $html);
        self::assertStringContainsString($expectedHash, $html);
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
        self::assertStringContainsString('DECL-ACM-2024-0001', $html);
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
                    exemptionReason: null,
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

    private function buildContextWithExemption(): DeclarationRenderContext
    {
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
                    contractId: 20,
                    contractReference: 'LCD-EXEMPT',
                    contractType: ContractType::Lcd,
                    startDate: '2024-05-10',
                    endDate: '2024-05-19',
                    daysInYearAssigned: 10,
                    vehicleId: 42,
                    vehicleLabel: 'Peugeot 308 · AB-123-CD',
                    vehicleFiscalSummary: 'M1 · WLTP 100 g · Euro 6',
                    co2Due: 0.0,
                    pollutantsDue: 0.0,
                    totalDue: 0.0,
                    clusterFingerprint: null,
                    clusterRiskCode: null,
                    clusterRiskLevel: null,
                    clusterDecision: null,
                    clusterJustification: null,
                    clusterDecisionRetainedFrom: null,
                    isOptedOut: false,
                    exemptionReason: 'Exonéré R-2024-021 · LCD courte durée (CIBS L. 421-129)',
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
            reference: 'DECL-ACM-2024-0003',
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
                    exemptionReason: null,
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
                    exemptionReason: null,
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
