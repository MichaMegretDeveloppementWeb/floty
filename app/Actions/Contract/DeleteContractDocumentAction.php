<?php

declare(strict_types=1);

namespace App\Actions\Contract;

use App\Contracts\Repositories\User\ContractDocument\ContractDocumentWriteRepositoryInterface;
use App\Models\ContractDocument;
use App\Services\Contract\ContractDocumentStorage;

/**
 * Suppression d'un document - hard-delete (DB row + fichier physique).
 *
 * Ordre : suppression du fichier physique d'abord, puis DB. Si la
 * suppression DB échoue après, on aura un orphan record sans fichier
 * (rare). L'inverse (DB d'abord puis fichier) laisserait un orphan
 * fichier sans record, plus difficile à nettoyer.
 */
final readonly class DeleteContractDocumentAction
{
    public function __construct(
        private ContractDocumentWriteRepositoryInterface $writer,
        private ContractDocumentStorage $storage,
    ) {}

    public function execute(ContractDocument $document): void
    {
        $this->storage->delete($document->storage_path);
        $this->writer->delete($document->id);
    }
}
