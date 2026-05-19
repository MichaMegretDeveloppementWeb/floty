<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Exceptions\Invoice\InvoicePdfAlreadyExistsException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Immutable storage for invoice PDFs (ADR-0008).
 *
 * Path layout · `invoices/{year}/{company_id}/{invoice_number}.pdf` on
 * the private local disk. Returns SHA-256 hex alongside the path for
 * tamper detection ({@see App\Models\Invoice::pdf_hash}).
 *
 * Write-once at the application layer · {@see store()} refuses to
 * overwrite existing files; uniqueness is also enforced by
 * {@see App\Actions\Invoice\GenerateInvoiceAction}.
 */
final readonly class InvoicePdfStorage
{
    public function __construct(
        private string $disk = 'local',
    ) {}

    /**
     * Persists the PDF binary and returns its path and hash.
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
            throw InvoicePdfAlreadyExistsException::forPath($path);
        }

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

    /**
     * Idempotent deletion. Used exclusively by
     * {@see App\Actions\Invoice\CancelInvoiceAction}, the only mutation
     * permitted by the immutability doctrine (ADR-0008).
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
     * Restores a previously deleted PDF from a binary backup. Bypasses
     * the write-once guard intentionally; used only by
     * {@see App\Actions\Invoice\RegenerateInvoiceAction} when a
     * regeneration fails and the old PDF must be reinstated to keep
     * DB ↔ filesystem consistency.
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
