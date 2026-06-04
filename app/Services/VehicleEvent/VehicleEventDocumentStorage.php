<?php

declare(strict_types=1);

namespace App\Services\VehicleEvent;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Storage gateway for unavailability documents (image or PDF proof).
 *
 * Uses the default filesystem disk (local in V1, swappable to S3 via
 * `FILESYSTEM_DISK`). Path layout ·
 * `unavailability-documents/{vehicle_event_id}/{uuid}.{ext}`. The
 * original extension is preserved so the browser can pick the right
 * viewer (jpg vs pdf vs png). Original filename is persisted separately
 * in DB.
 */
final readonly class VehicleEventDocumentStorage
{
    /**
     * Persists an uploaded file and returns metadata for DB storage.
     *
     * @return array{storage_path: string, sha256: string, size_bytes: int, mime_type: string, filename: string}
     */
    public function store(UploadedFile $file, int $vehicleEventId): array
    {
        $uuid = (string) Str::uuid();
        $extension = $this->safeExtension($file);
        $path = "unavailability-documents/{$vehicleEventId}/{$uuid}.{$extension}";

        Storage::disk($this->disk())->putFileAs(
            "unavailability-documents/{$vehicleEventId}",
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
     * Best-effort variant of {@see delete()} that swallows driver
     * exceptions and logs a warning. Used in compensation flows where
     * an orphan file is preferable to losing the originating exception.
     */
    public function safeDelete(string $storagePath): void
    {
        try {
            Storage::disk($this->disk())->delete($storagePath);
        } catch (Throwable $e) {
            Log::warning('VehicleEventDocumentStorage::safeDelete failed', [
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
     * Safe extension derived from the uploaded file. `getClientOriginalExtension()`
     * is user-supplied and falsifiable; we whitelist on allowed mimes
     * and fall back to the actual mime → extension mapping. MIME
     * validation is enforced upstream by the FormRequest; this is
     * defense-in-depth.
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
