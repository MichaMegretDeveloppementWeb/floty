<?php

declare(strict_types=1);

namespace App\Actions\Unavailability;

use App\Contracts\Repositories\User\UnavailabilityDocument\UnavailabilityDocumentWriteRepositoryInterface;
use App\Models\UnavailabilityDocument;
use App\Services\Unavailability\UnavailabilityDocumentStorage;

/**
 * Hard-deletes an unavailability document (DB row + physical file).
 *
 * Order: DB first, then physical file via `safeDelete`. A disk orphan
 * is acceptable (purgeable by a cleanup job); a DB orphan would surface
 * in the UI.
 */
final readonly class DeleteUnavailabilityDocumentAction
{
    public function __construct(
        private UnavailabilityDocumentWriteRepositoryInterface $writer,
        private UnavailabilityDocumentStorage $storage,
    ) {}

    public function execute(UnavailabilityDocument $document): void
    {
        $storagePath = $document->storage_path;

        $this->writer->delete($document->id);

        $this->storage->safeDelete($storagePath);
    }
}
