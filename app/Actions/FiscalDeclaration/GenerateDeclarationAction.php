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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Locks a `draft` (or `deferred`) declaration into `generated`.
 * Immutable snapshot: produces the documentary PDF annex, persists it
 * with its SHA-256 hash, and assigns a human-readable reference
 * `DECL-{shortCode}-{year}-{NNNN}`.
 *
 * Pipeline:
 *   1. Guards: declaration exists, status is `draft` or `deferred`
 *      (a deferred is a draft set aside), not obsolete.
 *   2. Recompute the preview (decisions-enriched clusters) to detect
 *      any new pending cluster introduced by a concurrent mutation.
 *   3. `snapshot = engine->compute(...)`: fiscal amounts post-decisions.
 *   4. `reference = referenceGenerator->generateFor(...)`: sequential
 *      readable number.
 *   5. Build the `DeclarationRenderContext` with preview + snapshot +
 *      reference + timestamp.
 *   6. Render the PDF (Blade + DomPDF in production, stub in tests).
 *   7. Persist the PDF + compute the SHA-256 hash.
 *   8. `writer->markAsGenerated(...)`: Draft → Generated and persist
 *      the four PDF fields + reference.
 *
 * Error cleanup: if disk persist succeeds but DB update fails, the
 * orphan PDF is left on disk (preservation pattern ADR-0015 § D9).
 * The exception is propagated.
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

        // Serialise the fiscal snapshot for DB persistence so future
        // consultation of the declaration shows exactly the amounts
        // computed at generation time, regardless of later contractual
        // mutations.
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
            // Orphan PDF on disk: disk persist succeeded but DB update
            // failed. Preservation pattern (ADR-0015 § D9): the PDF is
            // kept and a critical log line allows operator cleanup.
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

        Log::channel('declarations')->notice('FiscalDeclaration.generated', [
            'declaration_id' => $declaration->id,
            'company_id' => $declaration->company_id,
            'fiscal_year' => $declaration->fiscal_year,
            'reference' => $reference,
            'pdf_path' => $stored['path'],
            'total_due' => $snapshot->totalDue,
            'opt_outs_count' => count($snapshot->optOutContractIds),
            'actor_user_id' => Auth::id() ?? 0,
        ]);

        return $declaration->fresh();
    }

    private function guardCanGenerate(FiscalDeclaration $declaration): void
    {
        if ($declaration->is_obsolete) {
            throw new DomainException('Une déclaration obsolète doit être régénérée, pas générée.');
        }

        // `deferred` is a draft set aside; it remains eligible for
        // generation without an intermediate `draft` transition.
        if (! in_array($declaration->status, [FiscalDeclarationStatus::Draft, FiscalDeclarationStatus::Deferred], true)) {
            throw new DomainException(sprintf(
                'Génération impossible : seule une déclaration en brouillon (« draft » ou « deferred ») peut être générée (statut courant : %s).',
                $declaration->status->value,
            ));
        }
    }
}
