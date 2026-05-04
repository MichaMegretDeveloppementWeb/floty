<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Contract;

use App\Actions\Contract\BulkCreateContractsAction;
use App\Actions\Contract\DeleteContractAction;
use App\Actions\Contract\StoreContractAction;
use App\Actions\Contract\UpdateContractAction;
use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
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
    ) {}

    public function index(ContractIndexQueryData $query): Response
    {
        return Inertia::render('User/Contracts/Index/Index', [
            'contracts' => $this->contracts->listPaginated($query),
            'options' => $this->buildFormOptions(),
            'query' => $query,
            // Cf. note d'archi sur le bug placeholder : `hasAnyContract`
            // distingue « table intrinsèquement vide » du « filtre actif
            // retournant 0 » sans dériver depuis 3 sources désynchronisées.
            'hasAnyContract' => $this->contractRead->existsAny(),
        ]);
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
            ->with('toast-success', 'Contrat enregistré.');
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
            ->with('toast-success', 'Contrat mis à jour.');
    }

    public function destroy(int $contract): RedirectResponse
    {
        $this->deleteContract->execute($contract);

        return redirect()
            ->route('user.contracts.index')
            ->with('toast-success', 'Contrat supprimé.');
    }

    public function bulkStore(BulkStoreContractsData $data): RedirectResponse
    {
        $createdIds = $this->bulkCreateContracts->execute($data);
        $count = count($createdIds);

        return back()->with(
            'toast-success',
            sprintf('%d contrat%s enregistré%s.', $count, $count > 1 ? 's' : '', $count > 1 ? 's' : ''),
        );
    }
}
