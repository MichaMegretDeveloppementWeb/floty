<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\VehicleEvent;

use App\Actions\VehicleEvent\CreateVehicleEventAction;
use App\Actions\VehicleEvent\DeleteVehicleEventAction;
use App\Actions\VehicleEvent\UpdateVehicleEventAction;
use App\Actions\VehicleEvent\UploadVehicleEventDocumentAction;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Contracts\Repositories\User\VehicleEvent\VehicleEventReadRepositoryInterface;
use App\Data\User\VehicleEvent\StoreVehicleEventData;
use App\Data\User\VehicleEvent\UpdateVehicleEventData;
use App\Data\User\VehicleEvent\VehicleEventData;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Models\VehicleEventDocument;
use App\Services\Contract\ContractQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class VehicleEventController extends Controller
{
    public function __construct(
        private readonly VehicleEventReadRepositoryInterface $events,
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly ContractQueryService $contracts,
    ) {}

    /**
     * Render the dedicated "new event" form page for a vehicle. An optional
     * `?date=Y-m-d` query (set by the "+ Ajouter sur ce jour" timeline button)
     * pre-selects that day in the form; the busyDates window follows it.
     */
    public function create(int $vehicle, Request $request): Response
    {
        Gate::authorize('create', VehicleEvent::class);

        $initialDate = $this->resolveInitialDate($request->query('date'));
        $year = $initialDate !== null
            ? (int) substr($initialDate, 0, 4)
            : CarbonImmutable::now()->year;

        return Inertia::render('User/VehicleEvents/Create/Index', [
            'vehicle' => $this->vehicleHeader($vehicle),
            'busyDates' => $this->busyDatesForYear($vehicle, $year),
            'initialDate' => $initialDate,
        ]);
    }

    /**
     * Render the dedicated event detail page (read-only view, distinct from
     * the edit form). Edit and delete are operated from this page.
     */
    public function show(int $vehicle, int $vehicleEvent): Response
    {
        $event = $this->events->findForVehicleDetail($vehicle, $vehicleEvent);
        Gate::authorize('view', $event);

        return Inertia::render('User/VehicleEvents/Show/Index', [
            'vehicle' => $this->vehicleHeader($vehicle),
            'vehicleEvent' => VehicleEventData::fromModel($event),
        ]);
    }

    /**
     * Render the dedicated "edit event" form page.
     */
    public function edit(int $vehicle, int $vehicleEvent): Response
    {
        $event = $this->events->findForVehicleDetail($vehicle, $vehicleEvent);
        Gate::authorize('update', $event);

        return Inertia::render('User/VehicleEvents/Edit/Index', [
            'vehicle' => $this->vehicleHeader($vehicle),
            'vehicleEvent' => VehicleEventData::fromModel($event),
            'busyDates' => $this->busyDatesForYear($vehicle, $event->start_date->year),
        ]);
    }

    /**
     * Create a vehicle event, attaching any justification files queued in
     * the form during creation (atomic create flow, A2). The files travel
     * with the multipart create request and are validated by
     * {@see StoreVehicleEventData}; the per-file storage reuses the same
     * action as the standalone upload endpoint.
     */
    public function store(
        StoreVehicleEventData $data,
        Request $request,
        CreateVehicleEventAction $action,
        UploadVehicleEventDocumentAction $uploadDocument,
    ): RedirectResponse {
        Gate::authorize('create', VehicleEvent::class);

        $vehicleEvent = $action->execute($data);

        $files = $request->file('documents', []);
        if ($files !== []) {
            Gate::authorize('create', VehicleEventDocument::class);
            $userId = (int) auth()->id();
            foreach ($files as $file) {
                $uploadDocument->execute($vehicleEvent, $file, $userId);
            }
        }

        return redirect()
            ->route('user.vehicles.show', ['vehicle' => $vehicleEvent->vehicle_id, 'tab' => 'events'])
            ->with('toast-success', 'Événement ajouté.');
    }

    /**
     * Update an existing vehicle event.
     */
    public function update(
        int $vehicleEvent,
        UpdateVehicleEventData $data,
        UpdateVehicleEventAction $action,
    ): RedirectResponse {
        $model = $this->events->findById($vehicleEvent);
        Gate::authorize('update', $model);

        $action->execute($vehicleEvent, $data);

        return redirect()
            ->route('user.vehicles.events.show', [
                'vehicle' => $model->vehicle_id,
                'vehicleEvent' => $vehicleEvent,
            ])
            ->with('toast-success', 'Événement modifié.');
    }

    /**
     * Delete a vehicle event.
     */
    public function destroy(
        int $vehicleEvent,
        DeleteVehicleEventAction $action,
    ): RedirectResponse {
        $model = $this->events->findById($vehicleEvent);
        Gate::authorize('delete', $model);

        $vehicleId = $model->vehicle_id;

        $action->execute($vehicleEvent);

        // Delete is operated from the detail page; return to the timeline tab.
        return redirect()
            ->route('user.vehicles.show', ['vehicle' => $vehicleId, 'tab' => 'events'])
            ->with('toast-success', 'Événement supprimé.');
    }

    /**
     * Minimal vehicle identity for the form / detail page header.
     *
     * @return array{id: int, licensePlate: string, brand: string, model: string}
     */
    private function vehicleHeader(int $vehicle): array
    {
        $model = $this->vehicles->findById($vehicle);

        if ($model === null) {
            throw new NotFoundHttpException('Véhicule introuvable.');
        }

        return [
            'id' => $model->id,
            'licensePlate' => $model->license_plate,
            'brand' => $model->brand,
            'model' => $model->model,
        ];
    }

    /**
     * Validates the optional `?date=` query (timeline "+ Ajouter sur ce jour"):
     * returns a clean ISO `Y-m-d` string, or null when absent or malformed.
     */
    private function resolveInitialDate(mixed $raw): ?string
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $raw);
        } catch (\Exception) {
            return null;
        }

        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $raw) {
            return null;
        }

        return $raw;
    }

    /**
     * Busy dates (contract days) bounded to a single calendar year, for the
     * form pages' `DateRangePicker` conflict hint.
     *
     * @return list<string>
     */
    private function busyDatesForYear(int $vehicle, int $year): array
    {
        return $this->contracts->findDatesForVehicleInRange(
            $vehicle,
            sprintf('%d-01-01', $year),
            sprintf('%d-12-31', $year),
        );
    }
}
