<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Control;

use App\Actions\Control\Vehicle\RecordControlExecutionAction;
use App\Actions\Control\Vehicle\SaveVehicleControlOverrideAction;
use App\Actions\Control\Vehicle\SetVehicleControlStatusAction;
use App\Actions\Control\Vehicle\UploadControlExecutionDocumentAction;
use App\Contracts\Repositories\User\Control\ControlExecutionReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlExecutionWriteRepositoryInterface;
use App\Contracts\Repositories\User\Control\VehicleControlOverrideWriteRepositoryInterface;
use App\Data\User\Control\Vehicle\RecordControlExecutionData;
use App\Data\User\Control\Vehicle\SetVehicleControlStatusData;
use App\Data\User\Control\Vehicle\VehicleControlOverrideFormData;
use App\Http\Controllers\Controller;
use App\Models\ControlExecution;
use App\Models\ControlExecutionDocument;
use App\Models\VehicleControlOverride;
use App\Services\Control\ControlExecutionDocumentStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Per-vehicle regulatory controls (Chantier B / B2): mark a control "Fait" with
 * documents (generates a vehicle event), override / pause / disable / reset a
 * control, add a vehicle-specific control, edit level-2 recipients, browse the
 * execution history. All reads go through repositories (no Eloquent here).
 */
final class VehicleControlController extends Controller
{
    /**
     * Record a control execution ("Fait") and attach any documents.
     */
    public function recordExecution(
        int $vehicle,
        RecordControlExecutionData $data,
        Request $request,
        RecordControlExecutionAction $action,
        UploadControlExecutionDocumentAction $uploadDocument,
    ): RedirectResponse {
        Gate::authorize('create', ControlExecution::class);
        $this->assertSameVehicle($vehicle, $data->vehicleId);

        $userId = (int) Auth::id();
        $execution = $action->execute($data, $userId);

        $files = $request->file('documents', []);
        if (is_array($files) && $files !== []) {
            Gate::authorize('create', ControlExecutionDocument::class);
            foreach ($files as $file) {
                $uploadDocument->execute($execution, $file, $userId);
            }
        }

        return $this->backToTab($vehicle, 'Contrôle marqué comme fait.');
    }

    /**
     * Create an override of a global control or a vehicle-specific control.
     */
    public function storeOverride(
        int $vehicle,
        VehicleControlOverrideFormData $data,
        SaveVehicleControlOverrideAction $action,
    ): RedirectResponse {
        Gate::authorize('create', VehicleControlOverride::class);

        $action->execute($data, $vehicle);

        return $this->backToTab($vehicle, 'Contrôle du véhicule enregistré.');
    }

    /**
     * Update an existing per-vehicle control (override or specific).
     */
    public function updateOverride(
        int $vehicle,
        VehicleControlOverride $override,
        VehicleControlOverrideFormData $data,
        SaveVehicleControlOverrideAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $override);
        $this->assertOverrideVehicle($vehicle, $override);

        $action->execute($data, $vehicle, $override);

        return $this->backToTab($vehicle, 'Contrôle du véhicule mis à jour.');
    }

    /**
     * Reset a global control to its default (soft-delete the override row), or
     * remove a vehicle-specific control.
     */
    public function resetOverride(
        int $vehicle,
        VehicleControlOverride $override,
        VehicleControlOverrideWriteRepositoryInterface $writer,
    ): RedirectResponse {
        Gate::authorize('delete', $override);
        $this->assertOverrideVehicle($vehicle, $override);

        $writer->softDelete($override);

        return $this->backToTab($vehicle, 'Contrôle réinitialisé.');
    }

    /**
     * Toggle a control's status for the vehicle (pause / disable / reactivate).
     */
    public function setStatus(
        int $vehicle,
        SetVehicleControlStatusData $data,
        SetVehicleControlStatusAction $action,
    ): RedirectResponse {
        Gate::authorize('create', VehicleControlOverride::class);

        $action->execute($vehicle, $data);

        return $this->backToTab($vehicle, 'Statut du contrôle mis à jour.');
    }

    /**
     * Soft-delete a control execution.
     */
    public function deleteExecution(
        int $vehicle,
        ControlExecution $execution,
        ControlExecutionWriteRepositoryInterface $writer,
    ): RedirectResponse {
        Gate::authorize('delete', $execution);

        if ($execution->vehicle_id !== $vehicle) {
            throw new NotFoundHttpException('Exécution introuvable pour ce véhicule.');
        }

        $writer->softDelete($execution);

        return $this->backToTab($vehicle, 'Exécution supprimée.');
    }

    /**
     * Execution history (JSON, lazy) for one control on the vehicle.
     */
    public function history(int $vehicle, Request $request, ControlExecutionReadRepositoryInterface $reader): JsonResponse
    {
        Gate::authorize('viewAny', ControlExecution::class);

        $definitionId = $request->integer('definition_id') ?: null;
        $overrideId = $request->integer('override_id') ?: null;

        $executions = $reader->historyForControl($vehicle, $definitionId, $overrideId);

        return response()->json([
            'executions' => $executions->map(static fn (ControlExecution $execution): array => [
                'id' => $execution->id,
                'executedOn' => $execution->executed_on->toDateString(),
                'note' => $execution->note,
                'documents' => $execution->documents->map(static fn (ControlExecutionDocument $document): array => [
                    'id' => $document->id,
                    'filename' => $document->filename,
                    'sizeBytes' => $document->size_bytes,
                    'mimeType' => $document->mime_type,
                ])->all(),
            ])->all(),
        ]);
    }

    /**
     * Stream a control execution document with its original filename.
     */
    public function downloadDocument(
        int $vehicle,
        ControlExecutionDocument $document,
        ControlExecutionDocumentStorage $storage,
    ): StreamedResponse {
        Gate::authorize('view', $document);

        return $storage->streamResponse($document->storage_path, $document->filename);
    }

    private function assertSameVehicle(int $routeVehicleId, int $payloadVehicleId): void
    {
        if ($routeVehicleId !== $payloadVehicleId) {
            throw new NotFoundHttpException('Véhicule incohérent.');
        }
    }

    private function assertOverrideVehicle(int $vehicle, VehicleControlOverride $override): void
    {
        if ($override->vehicle_id !== $vehicle) {
            throw new NotFoundHttpException('Contrôle introuvable pour ce véhicule.');
        }
    }

    private function backToTab(int $vehicle, string $message): RedirectResponse
    {
        return redirect()
            ->route('user.vehicles.show', ['vehicle' => $vehicle, 'tab' => 'controls'])
            ->with('toast-success', $message);
    }
}
