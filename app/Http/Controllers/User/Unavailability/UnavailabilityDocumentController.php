<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Unavailability;

use App\Actions\Unavailability\DeleteUnavailabilityDocumentAction;
use App\Actions\Unavailability\UploadUnavailabilityDocumentAction;
use App\Contracts\Repositories\User\UnavailabilityDocument\UnavailabilityDocumentReadRepositoryInterface;
use App\Data\User\Unavailability\UnavailabilityDocumentData;
use App\Data\User\Unavailability\UploadUnavailabilityDocumentData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Contract\ContractDocumentController;
use App\Models\Unavailability;
use App\Models\UnavailabilityDocument;
use App\Services\Unavailability\UnavailabilityDocumentStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * HTTP endpoints for supporting documents attached to unavailabilities.
 *
 * Mirrors {@see ContractDocumentController}.
 */
final class UnavailabilityDocumentController extends Controller
{
    public function __construct(
        private readonly UnavailabilityDocumentReadRepositoryInterface $documents,
        private readonly UploadUnavailabilityDocumentAction $uploadAction,
        private readonly DeleteUnavailabilityDocumentAction $deleteAction,
        private readonly UnavailabilityDocumentStorage $storage,
    ) {}

    /**
     * Upload a new document to the given unavailability.
     */
    public function store(Unavailability $unavailability, UploadUnavailabilityDocumentData $data): JsonResponse
    {
        Gate::authorize('create', UnavailabilityDocument::class);

        $document = $this->uploadAction->execute(
            unavailability: $unavailability,
            file: $data->file,
            uploadedByUserId: (int) auth()->id(),
        );

        return response()->json(
            ['document' => UnavailabilityDocumentData::fromModel($document)],
            Response::HTTP_CREATED,
        );
    }

    /**
     * Stream a document binary for download.
     */
    public function show(int $unavailability, int $document): StreamedResponse
    {
        $doc = $this->documents->findById($document);

        if ($doc === null || $doc->unavailability_id !== $unavailability) {
            throw new NotFoundHttpException;
        }

        Gate::authorize('view', $doc);

        return $this->storage->streamResponse($doc->storage_path, $doc->filename);
    }

    /**
     * Delete a document and its stored file.
     */
    public function destroy(int $unavailability, int $document): Response
    {
        $doc = $this->documents->findById($document);

        if ($doc === null || $doc->unavailability_id !== $unavailability) {
            throw new NotFoundHttpException;
        }

        Gate::authorize('delete', $doc);

        $this->deleteAction->execute($doc);

        return response()->noContent();
    }
}
