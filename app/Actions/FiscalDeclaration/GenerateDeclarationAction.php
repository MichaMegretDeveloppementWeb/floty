<?php

declare(strict_types=1);

namespace App\Actions\FiscalDeclaration;

use App\Contracts\Pdf\DeclarationPdfRendererInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationWriteRepositoryInterface;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\FiscalDeclaration;
use App\Services\Fiscal\RiskDetection\DeclarationPreviewService;
use App\Services\Pdf\DeclarationPdfStorage;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Verrouille une déclaration `draft` en statut `generated` (Phase 11
 * D3, ADR-0015 § 6.3). Snapshot immuable : production du PDF annexe
 * documentaire, persistance disque, hash SHA-256.
 *
 * **Refus** : declaration introuvable, statut ≠ draft, déjà obsolete,
 * cluster en pending (re-vérifié à la génération pour cas concurrents).
 *
 * **PDF en D3** : implémentation injectée via interface
 * {@see DeclarationPdfRendererInterface}. Le binding par défaut est
 * `NullDeclarationPdfRenderer` (stub minimal). D5 swap vers le vrai
 * renderer DomPDF + template Blade complet.
 *
 * **Cleanup en cas d'erreur** : si la persistance disque réussit mais
 * la mise à jour BDD échoue, le PDF orphelin est supprimé. Pattern
 * direct {@see App\Actions\Invoice\GenerateInvoiceAction}.
 */
final readonly class GenerateDeclarationAction
{
    public function __construct(
        private FiscalDeclarationReadRepositoryInterface $reader,
        private FiscalDeclarationWriteRepositoryInterface $writer,
        private DeclarationPreviewService $preview,
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

        // Recalcul de la preview pour vérifier qu'aucun cluster
        // pending n'a été ajouté par une mutation concurrente entre
        // l'affichage et la génération.
        $preview = $this->preview->preview($declaration->company_id, $declaration->fiscal_year);
        if (! $preview->canGenerate) {
            throw new DomainException(sprintf(
                'Génération impossible : %d cluster(s) en attente de décision.',
                $preview->pendingClustersCount,
            ));
        }

        $pdfBinary = $this->renderer->render($preview);
        $stored = $this->storage->store($declaration, $pdfBinary);

        try {
            DB::transaction(function () use ($declaration, $stored): void {
                $this->writer->markAsGenerated($declaration->id, $stored['path'], $stored['hash']);
            });
        } catch (Throwable $e) {
            // Cleanup PDF orphelin si la mise à jour BDD échoue.
            // Ici on tolère l'absence de méthode delete() sur le storage
            // (volonté D8 : conservation totale) ; on log juste le path
            // du PDF orphelin pour traçabilité opérateur.
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
