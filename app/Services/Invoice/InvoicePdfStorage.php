<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Persistance immuable des PDF de factures (Phase 14.E V1.2).
 *
 * **Convention de chemin** : `invoices/{year}/{company_id}/{invoice_number}.pdf`
 *   - racine `private` (disque local — non public)
 *   - le segment `{company_id}` permet un cleanup sélectif si nécessaire
 *
 * **Hash SHA-256 hex** retourné en même temps que le path pour stockage
 * en base ({@see App\Models\Invoice::pdf_hash}) — sert d'empreinte
 * d'intégrité (détection de mutation post-émission).
 *
 * **Idempotence applicative** : `store()` refuse d'écraser un fichier
 * existant (cohérent doctrine immuabilité — l'unicité applicative est
 * portée par {@see App\Actions\Invoice\GenerateInvoiceAction}, ce
 * service est une défense en profondeur).
 */
final readonly class InvoicePdfStorage
{
    public function __construct(
        private string $disk = 'local',
    ) {}

    /**
     * Persiste le binaire PDF + retourne `path` + `hash`.
     *
     * @return array{path: string, hash: string}
     */
    public function store(
        int $year,
        int $companyId,
        string $invoiceNumber,
        string $pdfBinary,
    ): array {
        $path = $this->buildPath($year, $companyId, $invoiceNumber);

        $disk = $this->disk();

        if ($disk->exists($path)) {
            throw new \RuntimeException(
                "Refusing to overwrite existing invoice PDF at {$path}.",
            );
        }

        $disk->put($path, $pdfBinary);
        $hash = hash('sha256', $pdfBinary);

        return ['path' => $path, 'hash' => $hash];
    }

    /**
     * Lit un PDF persisté pour téléchargement. Renvoie `null` si le
     * fichier a disparu (cas pathologique : intervention manuelle sur
     * le filesystem). Le caller doit alors présenter un message d'erreur.
     */
    public function read(string $path): ?string
    {
        $disk = $this->disk();

        if (! $disk->exists($path)) {
            return null;
        }

        return $disk->get($path);
    }

    /**
     * Supprime le PDF du disque. Idempotent : aucune exception si le
     * fichier n'existe déjà plus (cas concurrence ou suppression
     * manuelle hors-app). Utilisé exclusivement par
     * {@see App\Actions\Invoice\CancelInvoiceAction} — l'annulation
     * d'une facture est la seule mutation autorisée par la doctrine
     * immuabilité (cf. ADR-0008).
     */
    public function delete(string $path): void
    {
        $this->disk()->delete($path);
    }

    public function buildPath(int $year, int $companyId, string $invoiceNumber): string
    {
        return sprintf('invoices/%d/%d/%s.pdf', $year, $companyId, $invoiceNumber);
    }

    /**
     * Restaure un PDF supprimé temporairement (chantier T4 / Phase 14.P).
     * Bypass délibéré du check write-once de {@see store()} : utilisé
     * exclusivement par {@see App\Actions\Invoice\RegenerateInvoiceAction}
     * pour rétablir l'ancien PDF si la régénération échoue, garantissant
     * la cohérence DB ↔ filesystem.
     */
    public function restoreFromBackup(string $path, string $binary): void
    {
        $this->disk()->put($path, $binary);
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->disk);
    }
}
