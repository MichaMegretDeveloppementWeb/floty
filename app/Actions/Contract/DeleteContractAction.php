<?php

declare(strict_types=1);

namespace App\Actions\Contract;

use App\Contracts\Repositories\User\Contract\ContractWriteRepositoryInterface;

/**
 * Suppression d'un contrat (soft delete).
 *
 * Le trigger MySQL anti-overlap exclut les contrats `deleted_at IS NOT NULL`,
 * donc une suppression libère immédiatement la plage pour la création
 * d'un nouveau contrat sur le même véhicule.
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
