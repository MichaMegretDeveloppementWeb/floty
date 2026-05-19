<?php

declare(strict_types=1);

namespace App\Actions\Unavailability;

use App\Contracts\Repositories\User\UnavailabilityDocument\UnavailabilityDocumentReadRepositoryInterface;
use App\Contracts\Repositories\User\UnavailabilityDocument\UnavailabilityDocumentWriteRepositoryInterface;
use App\Exceptions\Unavailability\TooManyUnavailabilityDocumentsException;
use App\Models\Unavailability;
use App\Models\UnavailabilityDocument;
use App\Services\Unavailability\UnavailabilityDocumentStorage;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Uploads a justification (image or PDF) for an unavailability.
 * Enforces the V1 limit (5 documents max per unavailability), stores
 * the file then persists the DB row.
 *
 * The filesystem is not transactional; on DB persist failure the file
 * is removed as best-effort compensation. A disk orphan stays
 * recoverable by a cleanup job, while a DB orphan would surface in
 * the UI.
 */
final readonly class UploadUnavailabilityDocumentAction
{
    public const int MAX_DOCUMENTS_PER_UNAVAILABILITY = 5;

    public function __construct(
        private UnavailabilityDocumentReadRepositoryInterface $reader,
        private UnavailabilityDocumentWriteRepositoryInterface $writer,
        private UnavailabilityDocumentStorage $storage,
    ) {}

    public function execute(Unavailability $unavailability, UploadedFile $file, int $uploadedByUserId): UnavailabilityDocument
    {
        $current = $this->reader->countForUnavailability($unavailability->id);

        if ($current >= self::MAX_DOCUMENTS_PER_UNAVAILABILITY) {
            throw TooManyUnavailabilityDocumentsException::limitReached(
                unavailabilityId: $unavailability->id,
                currentCount: $current,
                maxAllowed: self::MAX_DOCUMENTS_PER_UNAVAILABILITY,
            );
        }

        $meta = $this->storage->store($file, $unavailability->id);

        try {
            return $this->writer->create([
                'unavailability_id' => $unavailability->id,
                'filename' => $meta['filename'],
                'storage_path' => $meta['storage_path'],
                'size_bytes' => $meta['size_bytes'],
                'sha256' => $meta['sha256'],
                'mime_type' => $meta['mime_type'],
                'uploaded_by' => $uploadedByUserId,
            ]);
        } catch (Throwable $e) {
            $this->storage->safeDelete($meta['storage_path']);
            throw $e;
        }
    }
}
