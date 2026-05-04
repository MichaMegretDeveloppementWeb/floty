<?php

declare(strict_types=1);

namespace App\Actions\Contract;

use App\Contracts\Repositories\User\ContractDocument\ContractDocumentReadRepositoryInterface;
use App\Contracts\Repositories\User\ContractDocument\ContractDocumentWriteRepositoryInterface;
use App\Exceptions\Contract\TooManyContractDocumentsException;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Services\Contract\ContractDocumentStorage;
use Illuminate\Http\UploadedFile;

/**
 * Upload d'un PDF sur un contrat (chantier 04.N).
 *
 * Vérifie la limite V1 (5 documents max par contrat) avant de stocker
 * physiquement et de persister. Le stockage et la persistance ne sont
 * pas dans une transaction commune (le filesystem n'est pas
 * transactionnel) ; en cas d'échec DB après upload, le fichier
 * physique reste sur le disk - un job de cleanup pourra purger les
 * orphelins en V2 si besoin.
 */
final readonly class UploadContractDocumentAction
{
    public const int MAX_DOCUMENTS_PER_CONTRACT = 5;

    public function __construct(
        private ContractDocumentReadRepositoryInterface $reader,
        private ContractDocumentWriteRepositoryInterface $writer,
        private ContractDocumentStorage $storage,
    ) {}

    public function execute(Contract $contract, UploadedFile $file, int $uploadedByUserId): ContractDocument
    {
        $current = $this->reader->countForContract($contract->id);

        if ($current >= self::MAX_DOCUMENTS_PER_CONTRACT) {
            throw TooManyContractDocumentsException::limitReached(
                contractId: $contract->id,
                currentCount: $current,
                maxAllowed: self::MAX_DOCUMENTS_PER_CONTRACT,
            );
        }

        $meta = $this->storage->store($file, $contract->id);

        return $this->writer->create([
            'contract_id' => $contract->id,
            'filename' => $meta['filename'],
            'storage_path' => $meta['storage_path'],
            'size_bytes' => $meta['size_bytes'],
            'sha256' => $meta['sha256'],
            'mime_type' => $meta['mime_type'],
            'uploaded_by' => $uploadedByUserId,
        ]);
    }
}
