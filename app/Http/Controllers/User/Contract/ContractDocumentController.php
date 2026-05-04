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
use App\Services\Contract\ContractDocumentStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Endpoints HTTP des documents PDF joints aux contrats (chantier 04.N).
 *
 *   - POST   /app/contracts/{contract}/documents              → upload
 *   - GET    /app/contracts/{contract}/documents/{document}   → download
 *   - DELETE /app/contracts/{contract}/documents/{document}   → delete
 *
 * Pas de Policy formelle V1 - middleware `auth` au niveau du group de
 * routes suffit. Multi-tenant viendra en V2 avec une vérification
 * d'appartenance.
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

    public function store(int $contract, UploadContractDocumentData $data): JsonResponse
    {
        $contractModel = $this->contracts->findByIdWithRelations($contract);

        if ($contractModel === null) {
            throw new NotFoundHttpException;
        }

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

    public function show(int $contract, int $document): StreamedResponse
    {
        $doc = $this->documents->findById($document);

        if ($doc === null || $doc->contract_id !== $contract) {
            throw new NotFoundHttpException;
        }

        return $this->storage->streamResponse($doc->storage_path, $doc->filename);
    }

    public function destroy(int $contract, int $document): Response
    {
        $doc = $this->documents->findById($document);

        if ($doc === null || $doc->contract_id !== $contract) {
            throw new NotFoundHttpException;
        }

        $this->deleteAction->execute($doc);

        return response()->noContent();
    }
}
