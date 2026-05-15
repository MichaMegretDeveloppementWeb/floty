<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Unavailability;

use App\Actions\Unavailability\DeleteUnavailabilityDocumentAction;
use App\Actions\Unavailability\UploadUnavailabilityDocumentAction;
use App\Contracts\Repositories\User\UnavailabilityDocument\UnavailabilityDocumentReadRepositoryInterface;
use App\Data\User\Unavailability\UnavailabilityDocumentData;
use App\Data\User\Unavailability\UploadUnavailabilityDocumentData;
use App\Http\Controllers\Controller;
use App\Models\Unavailability;
use App\Models\UnavailabilityDocument;
use App\Services\Unavailability\UnavailabilityDocumentStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Endpoints HTTP des justificatifs joints aux indisponibilités (P1).
 *
 *   - POST   /app/unavailabilities/{unavailability}/documents              → upload
 *   - GET    /app/unavailabilities/{unavailability}/documents/{document}   → download
 *   - DELETE /app/unavailabilities/{unavailability}/documents/{document}   → delete
 *
 * Authorization via {@see UnavailabilityDocumentPolicy} · stub V1
 * retournant `true` partout (ADR-0011 § 7). V2 multi-tenant ajoutera
 * le scope par société propriétaire du véhicule parent.
 *
 * Aligné sur {@see ContractDocumentController}.
 */
final class UnavailabilityDocumentController extends Controller
{
    public function __construct(
        private readonly UnavailabilityDocumentReadRepositoryInterface $documents,
        private readonly UploadUnavailabilityDocumentAction $uploadAction,
        private readonly DeleteUnavailabilityDocumentAction $deleteAction,
        private readonly UnavailabilityDocumentStorage $storage,
    ) {}

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

    public function show(int $unavailability, int $document): StreamedResponse
    {
        $doc = $this->documents->findById($document);

        if ($doc === null || $doc->unavailability_id !== $unavailability) {
            throw new NotFoundHttpException;
        }

        Gate::authorize('view', $doc);

        return $this->storage->streamResponse($doc->storage_path, $doc->filename);
    }

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
