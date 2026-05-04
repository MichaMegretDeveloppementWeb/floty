<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Driver;

use App\Actions\Driver\AddDriverCompanyMembershipAction;
use App\Actions\Driver\CreateDriverAction;
use App\Actions\Driver\DetachDriverCompanyMembershipAction;
use App\Actions\Driver\LeaveDriverCompanyMembershipAction;
use App\Actions\Driver\SoftDeleteDriverAction;
use App\Actions\Driver\UpdateDriverAction;
use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Driver\DriverReadRepositoryInterface;
use App\Data\User\Driver\AddDriverCompanyMembershipData;
use App\Data\User\Driver\DriverIndexQueryData;
use App\Data\User\Driver\LeaveDriverCompanyMembershipData;
use App\Data\User\Driver\StoreDriverData;
use App\Data\User\Driver\UpdateDriverData;
use App\Exceptions\Driver\DriverCompanyMembershipBlockedException;
use App\Exceptions\Driver\DriverDeletionBlockedException;
use App\Exceptions\Driver\DriverMembershipNotFoundException;
use App\Exceptions\Driver\DriverNotFoundException;
use App\Exceptions\Driver\LeaveResolutionInvalidException;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Services\Driver\DriverQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class DriverController extends Controller
{
    public function __construct(
        private readonly DriverQueryService $drivers,
        private readonly CompanyReadRepositoryInterface $companyRead,
        private readonly DriverReadRepositoryInterface $driverRead,
    ) {}

    public function index(DriverIndexQueryData $query): Response
    {
        return Inertia::render('User/Drivers/Index/Index', [
            'drivers' => $this->drivers->listPaginated($query),
            'options' => [
                'companies' => $this->companyOptions(),
            ],
            'query' => $query,
            // `hasAnyDriver` = vraie réponse à « la table est-elle
            // intrinsèquement vide ? », indépendante du filtre actif.
            // Évite le flash placeholder pendant les transitions de
            // filtre (cf. note d'archi sur le bug placeholder).
            'hasAnyDriver' => $this->driverRead->existsAny(),
        ]);
    }

    public function show(Driver $driver): Response
    {
        $detail = $this->drivers->detail($driver->id);

        if ($detail === null) {
            throw DriverNotFoundException::byId($driver->id);
        }

        return Inertia::render('User/Drivers/Show/Index', [
            'driver' => $detail,
            'options' => [
                // Liste plate des companies pour peupler le picker du modal
                // d'ajout de membership (`AddDriverCompanyModal`). La modale
                // filtre côté front les companies déjà rattachées au driver.
                'companies' => $this->companyOptions(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('User/Drivers/Create/Index', [
            'companies' => $this->companyOptions(),
        ]);
    }

    public function store(StoreDriverData $data, CreateDriverAction $action): RedirectResponse
    {
        $driver = $action->execute($data);

        return redirect()
            ->route('user.drivers.show', $driver)
            ->with('toast-success', 'Conducteur créé.');
    }

    public function edit(Driver $driver): Response
    {
        return Inertia::render('User/Drivers/Edit/Index', [
            'driver' => [
                'id' => $driver->id,
                'firstName' => $driver->first_name,
                'lastName' => $driver->last_name,
            ],
        ]);
    }

    public function update(Driver $driver, UpdateDriverData $data, UpdateDriverAction $action): RedirectResponse
    {
        $action->execute($driver, $data);

        return redirect()
            ->route('user.drivers.show', $driver)
            ->with('toast-success', 'Conducteur mis à jour.');
    }

    public function destroy(Driver $driver, SoftDeleteDriverAction $action): RedirectResponse
    {
        try {
            $action->execute($driver);
        } catch (DriverDeletionBlockedException $e) {
            return back()->with('toast-error', $e->getUserMessage());
        }

        return redirect()
            ->route('user.drivers.index')
            ->with('toast-success', 'Conducteur supprimé.');
    }

    public function attachCompany(
        Driver $driver,
        AddDriverCompanyMembershipData $data,
        AddDriverCompanyMembershipAction $action,
    ): RedirectResponse {
        $action->execute($driver, $data);

        return back()->with('toast-success', 'Conducteur ajouté à l\'entreprise.');
    }

    public function leaveCompany(
        Driver $driver,
        int $companyId,
        LeaveDriverCompanyMembershipData $data,
        LeaveDriverCompanyMembershipAction $action,
    ): RedirectResponse {
        try {
            $action->execute($driver, $companyId, $data);
        } catch (DriverMembershipNotFoundException $e) {
            return back()->with('toast-error', $e->getUserMessage());
        } catch (LeaveResolutionInvalidException $e) {
            throw ValidationException::withMessages(['future_contracts_resolution' => [$e->getUserMessage()]]);
        }

        return back()->with('toast-success', 'Sortie enregistrée.');
    }

    public function detachCompany(
        Driver $driver,
        int $pivotId,
        DetachDriverCompanyMembershipAction $action,
    ): RedirectResponse {
        try {
            $action->execute($pivotId);
        } catch (DriverMembershipNotFoundException $e) {
            return back()->with('toast-error', $e->getUserMessage());
        } catch (DriverCompanyMembershipBlockedException $e) {
            return back()->with('toast-error', $e->getUserMessage());
        }

        return back()->with('toast-success', 'Rattachement supprimé.');
    }

    /**
     * Endpoint JSON consommé par la modal de sortie d'un driver d'une
     * entreprise (workflow Q6). Pour la `leftAt` choisie par l'utilisateur,
     * retourne la liste des contrats à venir du driver dans cette company
     * + pour chaque contrat la liste des drivers de remplacement
     * éligibles (actifs sur la période exacte). Le driver sortant est
     * exclu des candidats (interdit comme remplaçant de lui-même).
     */
    public function futureContractsForLeave(
        Driver $driver,
        int $companyId,
        Request $request,
    ): JsonResponse {
        $validated = $request->validate([
            'leftAt' => ['required', 'date_format:Y-m-d'],
        ]);

        $rows = $this->drivers->futureContractsForLeavePreview(
            $driver->id,
            $companyId,
            CarbonImmutable::parse($validated['leftAt']),
        );

        return response()->json(['contracts' => $rows]);
    }

    /**
     * Endpoint JSON consommé par le sélecteur driver du formulaire Contract.
     * Renvoie les drivers actifs dans la company sur la période demandée.
     */
    public function contractOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $options = $this->drivers->optionsForContract(
            (int) $validated['company_id'],
            CarbonImmutable::parse($validated['start_date']),
            CarbonImmutable::parse($validated['end_date']),
        );

        return response()->json(['drivers' => $options]);
    }

    /**
     * @return array<int, array{id: int, shortCode: string, legalName: string}>
     */
    private function companyOptions(): array
    {
        return $this->companyRead
            ->findAllForOptions()
            ->map(fn ($company): array => [
                'id' => $company->id,
                'shortCode' => $company->short_code,
                'legalName' => $company->legal_name,
            ])
            ->all();
    }
}
