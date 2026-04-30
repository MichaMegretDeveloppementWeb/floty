<?php

declare(strict_types=1);

namespace App\Repositories\User\ContractDocument;

use App\Contracts\Repositories\User\ContractDocument\ContractDocumentWriteRepositoryInterface;
use App\Models\ContractDocument;

final class ContractDocumentWriteRepository implements ContractDocumentWriteRepositoryInterface
{
    public function create(array $row): ContractDocument
    {
        return ContractDocument::create($row);
    }

    public function delete(int $id): void
    {
        ContractDocument::query()->where('id', $id)->delete();
    }
}
