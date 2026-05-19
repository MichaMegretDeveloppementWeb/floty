<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\ContractDocument;

use App\Actions\Contract\UploadContractDocumentAction;
use App\Models\ContractDocument;
use Illuminate\Database\Eloquent\Collection;

/**
 * ContractDocument reads · slim interface per ADR-0013.
 */
interface ContractDocumentReadRepositoryInterface
{
    public function findById(int $id): ?ContractDocument;

    /**
     * Documents of a contract, newest first (natural UX for the
     * Documents section).
     *
     * @return Collection<int, ContractDocument>
     */
    public function listForContract(int $contractId): Collection;

    /**
     * Number of existing documents for a contract · used by
     * {@see UploadContractDocumentAction} to enforce the 5-document cap
     * before insert.
     */
    public function countForContract(int $contractId): int;
}
