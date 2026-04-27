<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Assignment;

use App\Http\Controllers\Controller;
use App\Services\Assignment\AssignmentQueryService;
use App\Services\Company\CompanyQueryService;
use App\Services\Vehicle\VehicleQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page « Attribution rapide » — alternative au drawer de la vue
 * d'ensemble. Présente un formulaire plein écran : véhicule +
 * entreprise + calendrier multi-dates avec preview taxes induites.
 */
final class AssignmentController extends Controller
{
    public function __construct(
        private readonly VehicleQueryService $vehicles,
        private readonly CompanyQueryService $companies,
        private readonly AssignmentQueryService $assignments,
    ) {}

    public function index(): Response
    {
        return Inertia::render('User/Assignments/Index/Index', [
            'vehicles' => $this->vehicles->listForOptions(),
            'companies' => $this->companies->listForOptions(),
        ]);
    }

    /**
     * GET /app/assignments/vehicle-dates?vehicleId=X&year=YYYY
     */
    public function vehicleDates(Request $request): JsonResponse
    {
        $vehicleId = (int) $request->query('vehicleId');
        $year = (int) $request->query(
            'year',
            (string) config('floty.fiscal.current_year'),
        );
        if ($vehicleId <= 0) {
            abort(400, 'vehicleId requis.');
        }

        return response()->json(
            $this->assignments->vehicleDates($vehicleId, $year),
        );
    }
}
