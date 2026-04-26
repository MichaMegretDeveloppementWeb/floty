<?php

namespace App\Http\Controllers\User\Assignment;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page « Attribution rapide » — alternative au drawer de la vue d'ensemble.
 *
 * Présente un formulaire plein écran : véhicule + entreprise + calendrier
 * multi-dates avec preview taxes induites. Les attributions sont ensuite
 * créées en masse via `POST /app/planning/assignments`.
 *
 * La liste détaillée jour-par-jour a été retirée : elle est redondante
 * avec la heatmap annuelle qui apporte le même signal en plus dense.
 * Post-MVP : cette page pourra être transformée en modale globale
 * ouvrable depuis la TopBar.
 */
final class AssignmentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('User/Assignments/Index', [
            'vehicles' => Vehicle::query()
                ->whereNull('exit_date')
                ->orderBy('license_plate')
                ->get(['id', 'license_plate', 'brand', 'model'])
                ->map(static fn (Vehicle $v) => [
                    'id' => $v->id,
                    'licensePlate' => $v->license_plate,
                    'label' => sprintf(
                        '%s — %s %s',
                        $v->license_plate,
                        $v->brand,
                        $v->model,
                    ),
                ]),
            'companies' => Company::query()
                ->where('is_active', true)
                ->orderBy('legal_name')
                ->get(['id', 'legal_name', 'short_code', 'color'])
                ->map(static fn (Company $c) => [
                    'id' => $c->id,
                    'shortCode' => $c->short_code,
                    'legalName' => $c->legal_name,
                    'color' => $c->color->value,
                ]),
        ]);
    }

    /**
     * Retourne pour un véhicule donné :
     *   - `vehicleBusyDates` : dates où le véhicule est déjà attribué
     *     (toutes entreprises) → grisées dans le calendrier
     *   - `pairDates` : map companyId → list<dates> permettant au front de
     *     mettre en évidence les dates du couple courant sans les griser
     *
     * GET /app/assignments/vehicle-dates?vehicleId=X&year=YYYY
     *
     * Le paramètre `year` est optionnel — fallback sur
     * `config('floty.fiscal.current_year')` côté serveur.
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

        $assignments = Assignment::query()
            ->whereYear('date', $year)
            ->where('vehicle_id', $vehicleId)
            ->get(['company_id', 'date']);

        $busy = [];
        $byCompany = [];
        foreach ($assignments as $a) {
            $iso = $a->date->toDateString();
            $busy[] = $iso;
            $byCompany[(string) $a->company_id] ??= [];
            $byCompany[(string) $a->company_id][] = $iso;
        }

        return response()->json([
            'vehicleBusyDates' => array_values(array_unique($busy)),
            'pairDates' => $byCompany,
        ]);
    }
}
