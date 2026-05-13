<?php

declare(strict_types=1);

namespace App\Actions\FiscalDeclaration;

use App\Contracts\Pdf\DeclarationPdfRendererInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationWriteRepositoryInterface;
use App\Data\User\FiscalDeclaration\FiscalDeclarationSnapshotData;
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
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verrouille une déclaration `draft` en statut `generated` (Phase 11
 * D3 originale, refondue D5.5). Snapshot immuable : production du PDF
 * annexe documentaire complet + persistance disque + hash SHA-256 +
 * référence lisible `DECL-{shortCode}-{year}-{NNNN}`.
 *
 * **Pipeline D5.5** (élargi D5.10.Z) :
 *   1. Guards : déclaration existante, statut `draft` **ou** `deferred`
 *      (un deferred est un draft mis volontairement de côté), non obsolète.
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

        // Sérialise le snapshot fiscal pour la persistance BDD
        // (Phase 11 D5.7.5, audit B5). Garantit que la consultation
        // future de la déclaration affiche **exactement** les montants
        // calculés au moment de la génération, indépendamment des
        // mutations contractuelles ultérieures.
        $snapshotPayload = FiscalDeclarationSnapshotData::fromValueObject($snapshot)->toArray();

        try {
            DB::transaction(function () use ($declaration, $stored, $reference, $snapshotPayload): void {
                $this->writer->markAsGenerated(
                    $declaration->id,
                    $stored['path'],
                    $stored['hash'],
                    $reference,
                    $snapshotPayload,
                );
            });
        } catch (Throwable $e) {
            // PDF orphelin sur disque : la persistance disque a réussi mais
            // la mise à jour BDD a échoué. Pattern D8 conservation : on
            // **ne supprime pas** le PDF (immuabilité fiscale) mais on
            // log critique pour permettre cleanup opérateur si nécessaire.
            Log::critical('FiscalDeclaration: PDF stored on disk but database update failed (orphan PDF)', [
                'declaration_id' => $declaration->id,
                'company_id' => $declaration->company_id,
                'fiscal_year' => $declaration->fiscal_year,
                'reference' => $reference,
                'pdf_path' => $stored['path'],
                'pdf_hash' => $stored['hash'],
                'error_message' => $e->getMessage(),
                'error_class' => $e::class,
            ]);

            throw $e;
        }

        Log::info('FiscalDeclaration generated', [
            'declaration_id' => $declaration->id,
            'company_id' => $declaration->company_id,
            'fiscal_year' => $declaration->fiscal_year,
            'reference' => $reference,
            'pdf_path' => $stored['path'],
            'total_due' => $snapshot->totalDue,
            'opt_outs_count' => count($snapshot->optOutContractIds),
        ]);

        return $declaration->fresh();
    }

    private function guardCanGenerate(FiscalDeclaration $declaration): void
    {
        if ($declaration->is_obsolete) {
            throw new DomainException('Une déclaration obsolète doit être régénérée, pas générée.');
        }

        // Phase 13 D5.10.Z · `deferred` est un draft volontairement mis
        // de côté · il reste éligible à la génération sans transition
        // intermédiaire vers `draft`.
        if (! in_array($declaration->status, [FiscalDeclarationStatus::Draft, FiscalDeclarationStatus::Deferred], true)) {
            throw new DomainException(sprintf(
                'Génération impossible : seule une déclaration en brouillon (« draft » ou « deferred ») peut être générée (statut courant : %s).',
                $declaration->status->value,
            ));
        }
    }
}
