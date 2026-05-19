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
 * Uploads a PDF for a contract. Enforces the V1 limit (5 documents
 * max per contract), stores the file then persists the DB row.
 *
 * The filesystem is not transactional: the file is stored first, then
 * the DB row. If the DB persist fails, the physical file is removed as
 * compensation to avoid disk orphans; a failing compensation does not
 * suppress the original exception, since a disk orphan is recoverable
 * by a cleanup job while a DB orphan would be visible in the UI.
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
