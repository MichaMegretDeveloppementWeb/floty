<?php

declare(strict_types=1);

namespace App\Repositories\User\UnavailabilityDocument;

use App\Contracts\Repositories\User\UnavailabilityDocument\UnavailabilityDocumentWriteRepositoryInterface;
use App\Models\UnavailabilityDocument;

final class UnavailabilityDocumentWriteRepository implements UnavailabilityDocumentWriteRepositoryInterface
{
    public function create(array $row): UnavailabilityDocument
    {
        return UnavailabilityDocument::create($row);
    }

    public function delete(int $id): void
    {
        UnavailabilityDocument::query()->where('id', $id)->delete();
    }
}
