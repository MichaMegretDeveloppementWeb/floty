<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\UnavailabilityDocument;

use App\Actions\Unavailability\UploadUnavailabilityDocumentsAction;
use App\Models\UnavailabilityDocument;
use Illuminate\Database\Eloquent\Collection;

/**
 * UnavailabilityDocument reads · slim interface per ADR-0013.
 */
interface UnavailabilityDocumentReadRepositoryInterface
{
    public function findById(int $id): ?UnavailabilityDocument;

    /**
     * Documents of an unavailability, newest first (natural UX for the
     * Documents section).
     *
     * @return Collection<int, UnavailabilityDocument>
     */
    public function listForUnavailability(int $unavailabilityId): Collection;

    /**
     * Number of existing documents for an unavailability · used by
     * {@see UploadUnavailabilityDocumentsAction} to enforce the
     * 5-document cap before insert.
     */
    public function countForUnavailability(int $unavailabilityId): int;
}
