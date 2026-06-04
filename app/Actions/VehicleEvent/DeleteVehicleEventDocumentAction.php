<?php

declare(strict_types=1);

namespace App\Actions\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEventDocument\VehicleEventDocumentWriteRepositoryInterface;
use App\Models\VehicleEventDocument;
use App\Services\VehicleEvent\VehicleEventDocumentStorage;

/**
 * Hard-deletes an unavailability document (DB row + physical file).
 *
 * Order: DB first, then physical file via `safeDelete`. A disk orphan
 * is acceptable (purgeable by a cleanup job); a DB orphan would surface
 * in the UI.
 */
final readonly class DeleteVehicleEventDocumentAction
{
    public function __construct(
        private VehicleEventDocumentWriteRepositoryInterface $writer,
        private VehicleEventDocumentStorage $storage,
    ) {}

    public function execute(VehicleEventDocument $document): void
    {
        $storagePath = $document->storage_path;

        $this->writer->delete($document->id);

        $this->storage->safeDelete($storagePath);
    }
}
