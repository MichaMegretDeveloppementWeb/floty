<?php

declare(strict_types=1);

namespace App\Actions\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEventDocument\VehicleEventDocumentReadRepositoryInterface;
use App\Contracts\Repositories\User\VehicleEventDocument\VehicleEventDocumentWriteRepositoryInterface;
use App\Exceptions\VehicleEvent\TooManyVehicleEventDocumentsException;
use App\Models\VehicleEvent;
use App\Models\VehicleEventDocument;
use App\Services\VehicleEvent\VehicleEventDocumentStorage;
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
final readonly class UploadVehicleEventDocumentAction
{
    public const int MAX_DOCUMENTS_PER_UNAVAILABILITY = 5;

    public function __construct(
        private VehicleEventDocumentReadRepositoryInterface $reader,
        private VehicleEventDocumentWriteRepositoryInterface $writer,
        private VehicleEventDocumentStorage $storage,
    ) {}

    public function execute(VehicleEvent $vehicleEvent, UploadedFile $file, int $uploadedByUserId): VehicleEventDocument
    {
        $current = $this->reader->countForVehicleEvent($vehicleEvent->id);

        if ($current >= self::MAX_DOCUMENTS_PER_UNAVAILABILITY) {
            throw TooManyVehicleEventDocumentsException::limitReached(
                vehicleEventId: $vehicleEvent->id,
                currentCount: $current,
                maxAllowed: self::MAX_DOCUMENTS_PER_UNAVAILABILITY,
            );
        }

        $meta = $this->storage->store($file, $vehicleEvent->id);

        try {
            return $this->writer->create([
                'vehicle_event_id' => $vehicleEvent->id,
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
