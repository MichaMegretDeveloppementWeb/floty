<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\VehicleEventDocument;

use App\Actions\VehicleEvent\UploadVehicleEventDocumentsAction;
use App\Models\VehicleEventDocument;
use Illuminate\Database\Eloquent\Collection;

/**
 * VehicleEventDocument reads · slim interface per ADR-0013.
 */
interface VehicleEventDocumentReadRepositoryInterface
{
    public function findById(int $id): ?VehicleEventDocument;

    /**
     * Documents of an unavailability, newest first (natural UX for the
     * Documents section).
     *
     * @return Collection<int, VehicleEventDocument>
     */
    public function listForVehicleEvent(int $vehicleEventId): Collection;

    /**
     * Number of existing documents for an unavailability · used by
     * {@see UploadVehicleEventDocumentsAction} to enforce the
     * 5-document cap before insert.
     */
    public function countForVehicleEvent(int $vehicleEventId): int;
}
