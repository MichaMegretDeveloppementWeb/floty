<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\VehicleEventDocument;

use App\Models\VehicleEventDocument;

/**
 * VehicleEventDocument writes · slim interface per ADR-0013.
 *
 * No business decision here (5-document cap, hash, physical upload) ·
 * that is the role of the domain Actions.
 */
interface VehicleEventDocumentWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function create(array $row): VehicleEventDocument;

    /**
     * Hard-delete (no soft-delete on VehicleEventDocument in V1).
     */
    public function delete(int $id): void;
}
