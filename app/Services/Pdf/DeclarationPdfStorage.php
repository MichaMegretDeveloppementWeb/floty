<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\FiscalDeclaration;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Immutable storage for fiscal declaration PDF annexes (ADR-0015).
 *
 * Path layout · `declarations/{company_id}/{fiscal_year}/{declaration_id}.pdf`
 * on the private local disk. Returns SHA-256 hex alongside the path
 * for {@see FiscalDeclaration::generated_pdf_hash}.
 *
 * Total post-obsolescence retention · `MarkAsObsoleteAction` never
 * deletes the file; the binary remains on disk indefinitely, reachable
 * through `superseded_by_id`. No `delete()` is exposed.
 */
final readonly class DeclarationPdfStorage
{
    public function __construct(
        private string $disk = 'local',
    ) {}

    /**
     * Persists the PDF binary and returns its path and hash.
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
     * Reads a persisted PDF; returns `null` if the file is missing.
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
