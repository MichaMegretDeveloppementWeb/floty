<?php

declare(strict_types=1);

namespace App\Services\Contract;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Storage gateway for contract document files.
 *
 * Uses the default filesystem disk (local in V1, swappable to S3 via
 * `FILESYSTEM_DISK`). Path layout · `contract-documents/{contract_id}/{uuid}.pdf`.
 * The original client filename is persisted separately in DB.
 */
final readonly class ContractDocumentStorage
{
    /**
     * Persists an uploaded file and returns metadata for DB storage.
     *
     * @return array{storage_path: string, sha256: string, size_bytes: int, mime_type: string, filename: string}
     */
    public function store(UploadedFile $file, int $contractId): array
    {
        $uuid = (string) Str::uuid();
        $path = "contract-documents/{$contractId}/{$uuid}.pdf";

        Storage::disk($this->disk())->putFileAs(
            "contract-documents/{$contractId}",
            $file,
            "{$uuid}.pdf",
        );

        return [
            'storage_path' => $path,
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'size_bytes' => $file->getSize(),
            'mime_type' => $file->getMimeType() ?? 'application/pdf',
            'filename' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Idempotent file removal; missing files are accepted silently.
     */
    public function delete(string $storagePath): void
    {
        Storage::disk($this->disk())->delete($storagePath);
    }

    /**
     * Best-effort variant of {@see delete()} that swallows driver
     * exceptions and logs a warning. Used in compensation flows where
     * an orphan file is preferable to losing the originating exception.
     */
    public function safeDelete(string $storagePath): void
    {
        try {
            Storage::disk($this->disk())->delete($storagePath);
        } catch (Throwable $e) {
            Log::warning('ContractDocumentStorage::safeDelete failed', [
                'storage_path' => $storagePath,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Streamed download response carrying the original client filename.
     */
    public function streamResponse(string $storagePath, string $originalFilename): StreamedResponse
    {
        return Storage::disk($this->disk())->download($storagePath, $originalFilename);
    }

    private function disk(): string
    {
        /** @var string $disk */
        $disk = config('filesystems.default');

        return $disk;
    }
}
