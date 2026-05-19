<?php

declare(strict_types=1);

namespace App\Actions\Contract;

use App\Contracts\Repositories\User\ContractDocument\ContractDocumentWriteRepositoryInterface;
use App\Models\ContractDocument;
use App\Services\Contract\ContractDocumentStorage;

/**
 * Hard-deletes a contract document (DB row + physical file).
 *
 * Order: DB first, then physical file via `safeDelete`. If the DB
 * delete fails the call aborts without touching disk; if the physical
 * delete fails after a successful DB delete a warning is logged and
 * execution continues, since a silent file orphan is purgeable by a
 * cleanup job whereas an orphan row would regress the UX.
 */
final readonly class DeleteContractDocumentAction
{
    public function __construct(
        private ContractDocumentWriteRepositoryInterface $writer,
        private ContractDocumentStorage $storage,
    ) {}

    public function execute(ContractDocument $document): void
    {
        $storagePath = $document->storage_path;

        $this->writer->delete($document->id);

        $this->storage->safeDelete($storagePath);
    }
}
