<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Contract;

use App\Actions\Contract\DeleteContractDocumentAction;
use App\Actions\Contract\UploadContractDocumentAction;
use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\ContractDocument\ContractDocumentReadRepositoryInterface;
use App\Data\User\Contract\ContractDocumentData;
use App\Data\User\Contract\UploadContractDocumentData;
use App\Http\Controllers\Controller;
use App\Models\ContractDocument;
use App\Services\Contract\ContractDocumentStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * HTTP endpoints for PDF documents attached to contracts.
 */
final class ContractDocumentController extends Controller
{
    public function __construct(
        private readonly ContractReadRepositoryInterface $contracts,
        private readonly ContractDocumentReadRepositoryInterface $documents,
        private readonly UploadContractDocumentAction $uploadAction,
        private readonly DeleteContractDocumentAction $deleteAction,
        private readonly ContractDocumentStorage $storage,
    ) {}

    /**
     * Upload a new document to the given contract.
     */
    public function store(int $contract, UploadContractDocumentData $data): JsonResponse
    {
        $contractModel = $this->contracts->findByIdWithRelations($contract);

        if ($contractModel === null) {
            throw new NotFoundHttpException;
        }

        Gate::authorize('create', ContractDocument::class);

        $document = $this->uploadAction->execute(
            contract: $contractModel,
            file: $data->file,
            uploadedByUserId: (int) auth()->id(),
        );

        return response()->json(
            ['document' => ContractDocumentData::fromModel($document)],
            Response::HTTP_CREATED,
        );
    }

    /**
     * Stream a document binary for download.
     */
    public function show(int $contract, int $document): StreamedResponse
    {
        $doc = $this->documents->findById($document);

        if ($doc === null || $doc->contract_id !== $contract) {
            throw new NotFoundHttpException;
        }

        Gate::authorize('view', $doc);

        return $this->storage->streamResponse($doc->storage_path, $doc->filename);
    }

    /**
     * Delete a document and its stored file.
     */
    public function destroy(int $contract, int $document): Response
    {
        $doc = $this->documents->findById($document);

        if ($doc === null || $doc->contract_id !== $contract) {
            throw new NotFoundHttpException;
        }

        Gate::authorize('delete', $doc);

        $this->deleteAction->execute($doc);

        return response()->noContent();
    }
}
