<?php

declare(strict_types=1);

namespace App\Services\Control;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Storage gateway for control execution documents (Chantier B / B2). Mirror of
 * {@see App\Services\VehicleEvent\VehicleEventDocumentStorage}: default disk,
 * path layout `control-execution-documents/{control_execution_id}/{uuid}.{ext}`,
 * original extension preserved, original filename persisted in DB.
 */
final readonly class ControlExecutionDocumentStorage
{
    /**
     * Persists an uploaded file and returns metadata for DB storage.
     *
     * @return array{storage_path: string, sha256: string, size_bytes: int, mime_type: string, filename: string}
     */
    public function store(UploadedFile $file, int $controlExecutionId): array
    {
        $uuid = (string) Str::uuid();
        $extension = $this->safeExtension($file);
        $path = "control-execution-documents/{$controlExecutionId}/{$uuid}.{$extension}";

        Storage::disk($this->disk())->putFileAs(
            "control-execution-documents/{$controlExecutionId}",
            $file,
            "{$uuid}.{$extension}",
        );

        return [
            'storage_path' => $path,
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'size_bytes' => $file->getSize(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
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
     * Best-effort removal that swallows driver exceptions and logs a warning.
     * Used in compensation flows where an orphan file is preferable to losing
     * the originating exception.
     */
    public function safeDelete(string $storagePath): void
    {
        try {
            Storage::disk($this->disk())->delete($storagePath);
        } catch (Throwable $e) {
            Log::warning('ControlExecutionDocumentStorage::safeDelete failed', [
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

    /**
     * Safe extension whitelisted on allowed mimes (defense-in-depth; the mimes
     * are also validated upstream by the form DTO).
     */
    private function safeExtension(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

        if (in_array($ext, $allowed, true)) {
            return $ext;
        }

        return match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    private function disk(): string
    {
        /** @var string $disk */
        $disk = config('filesystems.default');

        return $disk;
    }
}
