<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\FiscalDeclaration;
use App\Services\Invoice\InvoicePdfStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Persistance immuable des PDF annexes de déclarations fiscales
 * (Phase 11 D3, ADR-0015 § 5.1 + D8). Pattern direct
 * {@see InvoicePdfStorage} (Phase 14.E).
 *
 * **Convention de chemin** :
 *   `declarations/{company_id}/{fiscal_year}/{declaration_id}.pdf`
 *   (disque `local`, racine privée).
 *
 * **Hash SHA-256 hex** retourné en même temps que le path pour
 * stockage en base ({@see FiscalDeclaration::generated_pdf_hash}).
 *
 * **Conservation totale post-obsolescence** (D8) : la suppression
 * d'un PDF n'est jamais déclenchée par `MarkAsObsoleteAction` ; le
 * fichier reste sur disque indéfiniment, accessible via
 * `superseded_by_id`. Aucune méthode `delete()` exposée donc.
 */
final readonly class DeclarationPdfStorage
{
    public function __construct(
        private string $disk = 'local',
    ) {}

    /**
     * Persiste le binaire PDF + retourne `path` + `hash`.
     *
     * @return array{path: string, hash: string}
     */
    public function store(FiscalDeclaration $declaration, string $pdfBinary): array
    {
        $path = $this->buildPath($declaration);

        $disk = $this->disk();
        $disk->put($path, $pdfBinary);
        $hash = hash('sha256', $pdfBinary);

        return ['path' => $path, 'hash' => $hash];
    }

    /**
     * Lit un PDF persisté. Renvoie `null` si le fichier a disparu
     * (cas pathologique : intervention manuelle hors-app).
     */
    public function read(string $path): ?string
    {
        $disk = $this->disk();
        if (! $disk->exists($path)) {
            return null;
        }

        return $disk->get($path);
    }

    public function buildPath(FiscalDeclaration $declaration): string
    {
        return sprintf(
            'declarations/%d/%d/%d.pdf',
            $declaration->company_id,
            $declaration->fiscal_year,
            $declaration->id,
        );
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->disk);
    }
}
