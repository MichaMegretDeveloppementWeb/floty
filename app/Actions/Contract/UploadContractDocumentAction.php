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
use Throwable;

/**
 * Upload d'un PDF sur un contrat (chantier 04.N).
 *
 * Vérifie la limite V1 (5 documents max par contrat) avant de stocker
 * physiquement et de persister.
 *
 * Le filesystem n'est pas transactionnel : on stocke d'abord le fichier
 * puis on persiste la ligne DB. Si la persistance DB échoue, on
 * compense en supprimant le fichier physique pour éviter de laisser un
 * orphelin disque (chantier γ.2). C'est un best-effort : si la
 * compensation échoue à son tour, on relance quand même l'exception
 * d'origine — l'utilisateur voit l'erreur, et un orphelin disque reste
 * récupérable par un job de cleanup, contrairement à un orphelin DB
 * qui serait visible dans l'UI.
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

        try {
            return $this->writer->create([
                'contract_id' => $contract->id,
                'filename' => $meta['filename'],
                'storage_path' => $meta['storage_path'],
                'size_bytes' => $meta['size_bytes'],
                'sha256' => $meta['sha256'],
                'mime_type' => $meta['mime_type'],
                'uploaded_by' => $uploadedByUserId,
            ]);
        } catch (Throwable $e) {
            $this->storage->safeDelete($meta['storage_path']);
            throw $e;
        }
    }
}
