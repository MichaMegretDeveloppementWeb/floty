<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\UnavailabilityDocument;

use App\Models\UnavailabilityDocument;

/**
 * UnavailabilityDocument writes · slim interface per ADR-0013.
 *
 * No business decision here (5-document cap, hash, physical upload) ·
 * that is the role of the domain Actions.
 */
interface UnavailabilityDocumentWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function create(array $row): UnavailabilityDocument;

    /**
     * Hard-delete (no soft-delete on UnavailabilityDocument in V1).
     */
    public function delete(int $id): void;
}
