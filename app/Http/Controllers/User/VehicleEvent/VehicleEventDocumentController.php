<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\VehicleEvent;

use App\Actions\VehicleEvent\DeleteVehicleEventDocumentAction;
use App\Actions\VehicleEvent\UploadVehicleEventDocumentAction;
use App\Contracts\Repositories\User\VehicleEventDocument\VehicleEventDocumentReadRepositoryInterface;
use App\Data\User\VehicleEvent\UploadVehicleEventDocumentData;
use App\Data\User\VehicleEvent\VehicleEventDocumentData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Contract\ContractDocumentController;
use App\Models\VehicleEvent;
use App\Models\VehicleEventDocument;
use App\Services\VehicleEvent\VehicleEventDocumentStorage;
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
final class VehicleEventDocumentController extends Controller
{
    public function __construct(
        private readonly VehicleEventDocumentReadRepositoryInterface $documents,
        private readonly UploadVehicleEventDocumentAction $uploadAction,
        private readonly DeleteVehicleEventDocumentAction $deleteAction,
        private readonly VehicleEventDocumentStorage $storage,
    ) {}

    /**
     * Upload a new document to the given unavailability.
     */
    public function store(VehicleEvent $vehicleEvent, UploadVehicleEventDocumentData $data): JsonResponse
    {
        Gate::authorize('create', VehicleEventDocument::class);

        $document = $this->uploadAction->execute(
            vehicleEvent: $vehicleEvent,
            file: $data->file,
            uploadedByUserId: (int) auth()->id(),
        );

        return response()->json(
            ['document' => VehicleEventDocumentData::fromModel($document)],
            Response::HTTP_CREATED,
        );
    }

    /**
     * Stream a document binary for download.
     */
    public function show(int $vehicleEvent, int $document): StreamedResponse
    {
        $doc = $this->documents->findById($document);

        if ($doc === null || $doc->vehicle_event_id !== $vehicleEvent) {
            throw new NotFoundHttpException;
        }

        Gate::authorize('view', $doc);

        return $this->storage->streamResponse($doc->storage_path, $doc->filename);
    }

    /**
     * Delete a document and its stored file.
     */
    public function destroy(int $vehicleEvent, int $document): Response
    {
        $doc = $this->documents->findById($document);

        if ($doc === null || $doc->vehicle_event_id !== $vehicleEvent) {
            throw new NotFoundHttpException;
        }

        Gate::authorize('delete', $doc);

        $this->deleteAction->execute($doc);

        return response()->noContent();
    }
}
