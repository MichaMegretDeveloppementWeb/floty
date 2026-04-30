<?php

declare(strict_types=1);

namespace App\Repositories\User\ContractDocument;

use App\Contracts\Repositories\User\ContractDocument\ContractDocumentReadRepositoryInterface;
use App\Models\ContractDocument;
use Illuminate\Database\Eloquent\Collection;

final class ContractDocumentReadRepository implements ContractDocumentReadRepositoryInterface
{
    public function findById(int $id): ?ContractDocument
    {
        return ContractDocument::query()->find($id);
    }

    public function listForContract(int $contractId): Collection
    {
        return ContractDocument::query()
            ->where('contract_id', $contractId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function countForContract(int $contractId): int
    {
        return ContractDocument::query()
            ->where('contract_id', $contractId)
            ->count();
    }
}
