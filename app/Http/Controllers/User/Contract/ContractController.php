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
use App\Data\User\Vehicle\VehicleFilterOptionData;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Services\Company\CompanyListingService;
use App\Services\Contract\ContractQueryService;
use App\Services\Driver\DriverQueryService;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Shared\Fiscal\FiscalYearContext;
use App\Services\Vehicle\VehicleListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Contract HTTP endpoints (slim, per ADR-0013).
 */
final class ContractController extends Controller
{
    public function __construct(
        private readonly ContractQueryService $contracts,
        private readonly ContractReadRepositoryInterface $contractRead,
        private readonly VehicleListingService $vehicles,
        private readonly CompanyListingService $companies,
        private readonly DriverQueryService $drivers,
        private readonly StoreContractAction $storeContract,
        private readonly UpdateContractAction $updateContract,
        private readonly DeleteContractAction $deleteContract,
        private readonly BulkCreateContractsAction $bulkCreateContracts,
        private readonly AvailableYearsResolver $availableYears,
        private readonly FiscalYearContext $fiscalYearContext,
    ) {}

    /**
     * List contracts. The fiscal pipeline is deferred so the initial
     * payload renders quickly; the costs prop arrives on the auto-fetch
     * follow-up.
     */
    public function index(ContractIndexQueryData $query): Response
    {
        Gate::authorize('viewAny', Contract::class);

        $this->applyDefaultYearIfMissing($query);

        $contracts = $this->contracts->listPaginatedSlim($query);
        $contractIds = array_map(static fn ($c): int => $c->id, $contracts->data);

        return Inertia::render('User/Contracts/Index/Index', [
            'contracts' => $contracts,
            'contractsCosts' => Inertia::defer(
                fn () => $this->contracts->costsForContractIds($contractIds),
            ),
            'options' => $this->buildSlimOptions(),
            'query' => $query,
            'hasAnyContract' => $this->contractRead->existsAny(),
            'yearScope' => YearScopeData::fromResolver($this->availableYears),
        ]);
    }

    /**
     * Slim options for the index dropdowns and the create/edit selectors.
     *
     * No fiscal pipeline runs here; ad-hoc calculations (full-year tax,
     * contract preview) are exposed through dedicated AJAX endpoints
     * triggered from the frontend on user interaction.
     *
     * @return array{
     *     vehicles: DataCollection<int, VehicleFilterOptionData>,
     *     companies: DataCollection<int, CompanyOptionData>,
     *     drivers: array<int, DriverOptionData>,
     * }
     */
    private function buildSlimOptions(): array
    {
        return [
            'vehicles' => $this->vehicles->listForLightSelector(),
            'companies' => $this->companies->listForOptions(),
            'drivers' => $this->drivers->listForOptions(),
        ];
    }

    /**
     * Default `year` to current when no temporal filter is set.
     *
     * Preserves "custom period" mode: if periodStart or periodEnd is set,
     * `year` is left untouched.
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

    /**
     * Render the contract detail page with tax + billing breakdowns.
     */
    public function show(int $contract): Response
    {
        $contractModel = Contract::query()->findOrFail($contract);
        Gate::authorize('view', $contractModel);

        $contractData = $this->contracts->findContractData($contract);

        if ($contractData === null) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('User/Contracts/Show/Index', [
            'contract' => $contractData,
            'taxBreakdown' => $this->contracts->findContractTaxBreakdown($contract),
            // Distinguishes a `null` taxBreakdown caused by an uncoded
            // fiscal year (false) from a missing VFC (true), so the panel
            // shows the right message.
            'taxFiscalYearSupported' => $this->fiscalYearContext->rangeSupported(
                (int) $contractModel->start_date->year,
                (int) $contractModel->end_date->year,
            ),
            'documents' => $this->contracts->listDocumentsForContract($contract),
            'billingBreakdown' => $this->contracts->findContractBillingBreakdown($contract),
        ]);
    }

    /**
     * Render the contract creation form.
     */
    public function create(): Response
    {
        Gate::authorize('create', Contract::class);

        return Inertia::render('User/Contracts/Create/Index', [
            'options' => $this->buildSlimOptions(),
            'busyDatesByVehicleId' => $this->contracts->busyDatesByVehicleAroundToday(),
        ]);
    }

    /**
     * Persist a new contract.
     */
    public function store(StoreContractData $data): RedirectResponse
    {
        Gate::authorize('create', Contract::class);

        $contract = $this->storeContract->execute($data);

        return redirect()
            ->route('user.contracts.show', ['contract' => $contract->id])
            ->with('toast-success', 'Location enregistrée.');
    }

    /**
     * Render the contract edit form.
     */
    public function edit(int $contract): Response
    {
        $contractModel = Contract::query()->findOrFail($contract);
        Gate::authorize('update', $contractModel);

        $contractData = $this->contracts->findContractData($contract);

        if ($contractData === null) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('User/Contracts/Edit/Index', [
            'contract' => $contractData,
            'options' => $this->buildSlimOptions(),
            'busyDatesByVehicleId' => $this->contracts->busyDatesByVehicleAroundToday(
                excludeContractId: $contract,
            ),
        ]);
    }

    /**
     * Update an existing contract.
     */
    public function update(int $contract, UpdateContractData $data): RedirectResponse
    {
        $contractModel = Contract::query()->findOrFail($contract);
        Gate::authorize('update', $contractModel);

        $this->updateContract->execute($contract, $data);

        return redirect()
            ->route('user.contracts.show', ['contract' => $contract])
            ->with('toast-success', 'Location mise à jour.');
    }

    /**
     * Delete a contract.
     */
    public function destroy(int $contract): RedirectResponse
    {
        $contractModel = Contract::query()->findOrFail($contract);
        Gate::authorize('delete', $contractModel);

        $this->deleteContract->execute($contract);

        return redirect()
            ->route('user.contracts.index')
            ->with('toast-success', 'Location supprimée.');
    }

    /**
     * Create multiple contracts sharing the same period in a single call.
     */
    public function bulkStore(BulkStoreContractsData $data): RedirectResponse
    {
        Gate::authorize('create', Contract::class);

        $createdIds = $this->bulkCreateContracts->execute($data);
        $count = count($createdIds);

        return back()->with(
            'toast-success',
            sprintf('%d location%s enregistrée%s.', $count, $count > 1 ? 's' : '', $count > 1 ? 's' : ''),
        );
    }
}
