<?php

declare(strict_types=1);

namespace App\Actions\FiscalDeclaration;

use App\Contracts\Pdf\DeclarationPdfRendererInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationWriteRepositoryInterface;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Fiscal\ValueObjects\DeclarationRenderContext;
use App\Models\FiscalDeclaration;
use App\Services\Fiscal\Declaration\DeclarationFiscalEngine;
use App\Services\Fiscal\Declaration\DeclarationReferenceGenerator;
use App\Services\Fiscal\RiskDetection\DeclarationPreviewService;
use App\Services\Pdf\DeclarationPdfStorage;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Verrouille une déclaration `draft` en statut `generated` (Phase 11
 * D3 originale, refondue D5.5). Snapshot immuable : production du PDF
 * annexe documentaire complet + persistance disque + hash SHA-256 +
 * référence lisible `DECL-{shortCode}-{year}-{NNNN}`.
 *
 * **Pipeline D5.5** :
 *   1. Guards : déclaration existante, statut `draft`, non obsolète.
 *   2. Recalcul de la `preview` (clusters re-détectés enrichis decisions)
 *      pour vérifier qu'aucun cluster pending n'a été ajouté par une
 *      mutation concurrente entre l'affichage et la génération.
 *   3. `snapshot = engine->compute(companyId, year)` (D5.2) : montants
 *      fiscaux post-décisions Requalified.
 *   4. `reference = referenceGenerator->generateFor(companyId, year)`
 *      (D5.3) : numéro lisible séquentiel.
 *   5. Construction du `DeclarationRenderContext` (D5.4) embarquant
 *      preview + snapshot + reference + horodatage.
 *   6. `pdfBinary = renderer->render(context)` : Blade + DomPDF en
 *      production, stub en tests qui le bindent explicitement.
 *   7. `storage->store(declaration, pdfBinary)` : persistance disque
 *      + hash SHA-256.
 *   8. `writer->markAsGenerated(...)` : transition Draft → Generated +
 *      persistance des 4 champs PDF + référence.
 *
 * **Cleanup en cas d'erreur** : si la persistance disque réussit mais
 * la mise à jour BDD échoue, le PDF orphelin est laissé sur disque
 * (pattern conservation D8 ADR-0015 § D9). L'erreur est propagée.
 */
final readonly class GenerateDeclarationAction
{
    public function __construct(
        private FiscalDeclarationReadRepositoryInterface $reader,
        private FiscalDeclarationWriteRepositoryInterface $writer,
        private DeclarationPreviewService $preview,
        private DeclarationFiscalEngine $engine,
        private DeclarationReferenceGenerator $referenceGenerator,
        private DeclarationPdfRendererInterface $renderer,
        private DeclarationPdfStorage $storage,
    ) {}

    public function execute(int $declarationId): FiscalDeclaration
    {
        $declaration = $this->reader->findById($declarationId);
        if ($declaration === null) {
            throw new DomainException(sprintf('Déclaration %d introuvable.', $declarationId));
        }

        $this->guardCanGenerate($declaration);

        $preview = $this->preview->preview($declaration->company_id, $declaration->fiscal_year);
        if (! $preview->canGenerate) {
            throw new DomainException(sprintf(
                'Génération impossible : %d cluster(s) en attente de décision.',
                $preview->pendingClustersCount,
            ));
        }

        $snapshot = $this->engine->compute($declaration->company_id, $declaration->fiscal_year);
        $reference = $this->referenceGenerator->generateFor(
            $declaration->company_id,
            $declaration->fiscal_year,
        );

        $context = new DeclarationRenderContext(
            preview: $preview,
            snapshot: $snapshot,
            reference: $reference,
            generatedAt: CarbonImmutable::now(),
        );

        $pdfBinary = $this->renderer->render($context);
        $stored = $this->storage->store($declaration, $pdfBinary);

        try {
            DB::transaction(function () use ($declaration, $stored, $reference): void {
                $this->writer->markAsGenerated(
                    $declaration->id,
                    $stored['path'],
                    $stored['hash'],
                    $reference,
                );
            });
        } catch (Throwable $e) {
            // PDF orphelin laissé sur disque (pattern D8 conservation).
            throw $e;
        }

        return $declaration->fresh();
    }

    private function guardCanGenerate(FiscalDeclaration $declaration): void
    {
        if ($declaration->is_obsolete) {
            throw new DomainException('Une déclaration obsolète doit être régénérée, pas générée.');
        }

        if ($declaration->status !== FiscalDeclarationStatus::Draft) {
            throw new DomainException(sprintf(
                'Génération impossible : seule une déclaration en statut « draft » peut être générée (statut courant : %s).',
                $declaration->status->value,
            ));
        }
    }
}
