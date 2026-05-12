<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Contract;

use App\Actions\Contract\BulkCreateContractsAction;
use App\Actions\Contract\DeleteContractAction;
use App\Actions\Contract\StoreContractAction;
use App\Actions\Contract\UpdateContractAction;
use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Data\Shared\YearScopeData;
use App\Data\User\Company\CompanyOptionData;
use App\Data\User\Contract\BulkStoreContractsData;
use App\Data\User\Contract\ContractIndexQueryData;
use App\Data\User\Contract\StoreContractData;
use App\Data\User\Contract\UpdateContractData;
use App\Data\User\Driver\DriverOptionData;
use App\Data\User\Vehicle\VehicleOptionData;
use App\Http\Controllers\Controller;
use App\Services\Company\CompanyQueryService;
use App\Services\Contract\ContractQueryService;
use App\Services\Driver\DriverQueryService;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Vehicle\VehicleQueryService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Endpoints HTTP du domaine Contract - slim conforme ADR-0013.
 *
 * Les pages Vue cibles `User/Contracts/*` sont créées au chantier 04.G ;
 * en attendant, les méthodes `index`, `create`, `show`, `edit` rendent
 * des composants Inertia placeholder qui seront remplacés par les
 * vraies pages.
 */
final class ContractController extends Controller
{
    public function __construct(
        private readonly ContractQueryService $contracts,
        private readonly ContractReadRepositoryInterface $contractRead,
        private readonly VehicleQueryService $vehicles,
        private readonly CompanyQueryService $companies,
        private readonly DriverQueryService $drivers,
        private readonly StoreContractAction $storeContract,
        private readonly UpdateContractAction $updateContract,
        private readonly DeleteContractAction $deleteContract,
        private readonly BulkCreateContractsAction $bulkCreateContracts,
        private readonly AvailableYearsResolver $availableYears,
    ) {}

    public function index(ContractIndexQueryData $query): Response
    {
        // Sync pill UI ↔ filtre serveur (chantier C) : si l'utilisateur
        // arrive sans `year` ni `periodStart/End`, on impose `year =
        // currentYear` côté DTO. Cohérent avec CompanyController et
        // VehicleController (doctrine temporelle chantier η Phase 3).
        $this->applyDefaultYearIfMissing($query);

        return Inertia::render('User/Contracts/Index/Index', [
            'contracts' => $this->contracts->listPaginated($query),
            'options' => $this->buildFormOptions(),
            'query' => $query,
            // Cf. note d'archi sur le bug placeholder : `hasAnyContract`
            // distingue « table intrinsèquement vide » du « filtre actif
            // retournant 0 » sans dériver depuis 3 sources désynchronisées.
            'hasAnyContract' => $this->contractRead->existsAny(),
            'yearScope' => YearScopeData::fromResolver($this->availableYears),
        ]);
    }

    /**
     * Mute `$query->year` vers `currentYear` quand l'utilisateur arrive
     * sans aucun filtre temporel (`year` + `periodStart` + `periodEnd`
     * tous null). Préserve le mode « Période personnalisée » (si
     * `periodStart` ou `periodEnd` est posé, on ne touche pas à `year`).
     *
     * Spatie Data DTOs sont mutables (props publiques) · c'est le
     * mécanisme prévu pour ce genre de post-traitement avant rendu.
     */
    private function applyDefaultYearIfMissing(ContractIndexQueryData $query): void
    {
        if (
            $query->year === null
            && $query->periodStart === null
            && $query->periodEnd === null
        ) {
            $query->year = $this->availableYears->currentYear();
        }
    }

    public function show(int $contract): Response
    {
        $contractData = $this->contracts->findContractData($contract);

        if ($contractData === null) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('User/Contracts/Show/Index', [
            'contract' => $contractData,
            'taxBreakdown' => $this->contracts->findContractTaxBreakdown($contract),
            'documents' => $this->contracts->listDocumentsForContract($contract),
            // Phase 14.D V1.2 · récap facturation contrat-isolé.
            'billingBreakdown' => $this->contracts->findContractBillingBreakdown($contract),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('User/Contracts/Create/Index', [
            'options' => $this->buildFormOptions(),
            'busyDatesByVehicleId' => $this->contracts->busyDatesByVehicleAroundToday(),
        ]);
    }

    public function store(StoreContractData $data): RedirectResponse
    {
        $contract = $this->storeContract->execute($data);

        return redirect()
            ->route('user.contracts.show', ['contract' => $contract->id])
            ->with('toast-success', 'Location enregistrée.');
    }

    public function edit(int $contract): Response
    {
        $contractData = $this->contracts->findContractData($contract);

        if ($contractData === null) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('User/Contracts/Edit/Index', [
            'contract' => $contractData,
            'options' => $this->buildFormOptions(),
            'busyDatesByVehicleId' => $this->contracts->busyDatesByVehicleAroundToday(
                excludeContractId: $contract,
            ),
        ]);
    }

    /**
     * @return array{
     *     vehicles: DataCollection<int, VehicleOptionData>,
     *     companies: DataCollection<int, CompanyOptionData>,
     *     drivers: array<int, DriverOptionData>,
     * }
     */
    private function buildFormOptions(): array
    {
        return [
            'vehicles' => $this->vehicles->listForOptions(),
            'companies' => $this->companies->listForOptions(),
            'drivers' => $this->drivers->listForOptions(),
        ];
    }

    public function update(int $contract, UpdateContractData $data): RedirectResponse
    {
        $this->updateContract->execute($contract, $data);

        return redirect()
            ->route('user.contracts.show', ['contract' => $contract])
            ->with('toast-success', 'Location mise à jour.');
    }

    public function destroy(int $contract): RedirectResponse
    {
        $this->deleteContract->execute($contract);

        return redirect()
            ->route('user.contracts.index')
            ->with('toast-success', 'Location supprimée.');
    }

    public function bulkStore(BulkStoreContractsData $data): RedirectResponse
    {
        $createdIds = $this->bulkCreateContracts->execute($data);
        $count = count($createdIds);

        return back()->with(
            'toast-success',
            sprintf('%d location%s enregistrée%s.', $count, $count > 1 ? 's' : '', $count > 1 ? 's' : ''),
        );
    }
}
