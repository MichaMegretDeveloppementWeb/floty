<?php

declare(strict_types=1);

namespace App\Repositories\User\VehicleEventDocument;

use App\Contracts\Repositories\User\VehicleEventDocument\VehicleEventDocumentWriteRepositoryInterface;
use App\Models\VehicleEventDocument;

final class VehicleEventDocumentWriteRepository implements VehicleEventDocumentWriteRepositoryInterface
{
    public function create(array $row): VehicleEventDocument
    {
        return VehicleEventDocument::create($row);
    }

    public function delete(int $id): void
    {
        VehicleEventDocument::query()->where('id', $id)->delete();
    }
}
