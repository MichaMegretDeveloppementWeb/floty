<?php

declare(strict_types=1);

namespace App\Actions\Control\Vehicle;

use App\Contracts\Repositories\User\Control\ControlExecutionDocumentReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlExecutionDocumentWriteRepositoryInterface;
use App\Exceptions\Control\TooManyControlExecutionDocumentsException;
use App\Models\ControlExecution;
use App\Models\ControlExecutionDocument;
use App\Services\Control\ControlExecutionDocumentStorage;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Uploads a supporting document for a control execution (Chantier B / B2).
 * Mirrors {@see App\Actions\VehicleEvent\UploadVehicleEventDocumentAction}:
 * enforces the V1 cap (5 per execution), stores the file then persists the row,
 * with best-effort file compensation on DB failure.
 */
final readonly class UploadControlExecutionDocumentAction
{
    public const int MAX_DOCUMENTS_PER_EXECUTION = 5;

    public function __construct(
        private ControlExecutionDocumentReadRepositoryInterface $reader,
        private ControlExecutionDocumentWriteRepositoryInterface $writer,
        private ControlExecutionDocumentStorage $storage,
    ) {}

    public function execute(ControlExecution $execution, UploadedFile $file, int $uploadedByUserId): ControlExecutionDocument
    {
        $current = $this->reader->countForExecution($execution->id);

        if ($current >= self::MAX_DOCUMENTS_PER_EXECUTION) {
            throw TooManyControlExecutionDocumentsException::limitReached(
                controlExecutionId: $execution->id,
                currentCount: $current,
                maxAllowed: self::MAX_DOCUMENTS_PER_EXECUTION,
            );
        }

        $meta = $this->storage->store($file, $execution->id);

        try {
            return $this->writer->create([
                'control_execution_id' => $execution->id,
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
