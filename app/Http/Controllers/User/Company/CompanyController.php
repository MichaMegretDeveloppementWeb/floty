<?php

namespace App\Http\Controllers\User\Company;

use App\Enums\Company\CompanyColor;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Company\StoreCompanyRequest;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\Vehicle;
use App\Services\Fiscal\FiscalCalculator;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyController extends Controller
{
    public function __construct(private readonly FiscalCalculator $calculator) {}

    public function index(): Response
    {
        $year = (int) config('floty.fiscal.current_year');

        // Agrégat annuel : pour chaque couple (vehicle, company), le cumul
        // de jours utilisés — puis par entreprise, somme des jours et des
        // taxes induites (via le moteur fiscal).
        $cumulByPair = [];
        Assignment::query()
            ->whereYear('date', $year)
            ->get(['vehicle_id', 'company_id'])
            ->each(function ($a) use (&$cumulByPair): void {
                $key = $a->vehicle_id.'|'.$a->company_id;
                $cumulByPair[$key] = ($cumulByPair[$key] ?? 0) + 1;
            });

        $daysByCompany = [];
        $taxByCompany = [];
        $vehicleCache = [];
        foreach ($cumulByPair as $key => $days) {
            [$vehicleId, $companyId] = explode('|', (string) $key);
            $vehicle = $vehicleCache[$vehicleId] ??= Vehicle::find($vehicleId);
            if ($vehicle === null) {
                continue;
            }
            $breakdown = $this->calculator->calculate($vehicle, $days, $days, $year);
            $daysByCompany[$companyId] = ($daysByCompany[$companyId] ?? 0) + $days;
            $taxByCompany[$companyId] = ($taxByCompany[$companyId] ?? 0.0) + $breakdown->totalDue;
        }

        $companies = Company::query()
            ->orderBy('legal_name')
            ->get()
            ->map(static fn (Company $c) => [
                'id' => $c->id,
                'legalName' => $c->legal_name,
                'shortCode' => $c->short_code,
                'color' => $c->color->value,
                'siren' => $c->siren,
                'city' => $c->city,
                'isActive' => $c->is_active,
                'daysUsed' => $daysByCompany[(string) $c->id] ?? 0,
                'annualTaxDue' => round($taxByCompany[(string) $c->id] ?? 0.0, 2),
            ]);

        return Inertia::render('User/Companies/Index', [
            'companies' => $companies,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('User/Companies/Create', [
            'colors' => array_map(
                static fn (CompanyColor $c): array => [
                    'value' => $c->value,
                    'label' => $c->label(),
                ],
                CompanyColor::cases(),
            ),
        ]);
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        Company::create($request->validated());

        return redirect()
            ->route('user.companies.index')
            ->with('toast-success', 'Entreprise créée.');
    }
}
