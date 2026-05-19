<?php

declare(strict_types=1);

namespace App\Actions\Contract;

use App\Contracts\Repositories\User\Contract\ContractWriteRepositoryInterface;

/**
 * Soft-deletes a contract. The MySQL anti-overlap trigger ignores
 * rows with `deleted_at IS NOT NULL`, so deletion immediately frees
 * the range for a new contract on the same vehicle.
 */
final readonly class DeleteContractAction
{
    public function __construct(
        private ContractWriteRepositoryInterface $writer,
    ) {}

    public function execute(int $contractId): void
    {
        $this->writer->delete($contractId);
    }
}
