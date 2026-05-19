<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Driver;

use App\Actions\Driver\CreateDriverAction;
use App\Actions\Driver\SoftDeleteDriverAction;
use App\Actions\Driver\UpdateDriverAction;
use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Driver\DriverReadRepositoryInterface;
use App\Data\User\Driver\DriverIndexQueryData;
use App\Data\User\Driver\StoreDriverData;
use App\Data\User\Driver\UpdateDriverData;
use App\Exceptions\Driver\DriverDeletionBlockedException;
use App\Exceptions\Driver\DriverNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Services\Driver\DriverQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Driver CRUD plus JSON option lookups consumed by the contract form.
 */
final class DriverController extends Controller
{
    public function __construct(
        private readonly DriverQueryService $drivers,
        private readonly CompanyReadRepositoryInterface $companyRead,
        private readonly DriverReadRepositoryInterface $driverRead,
    ) {}

    /**
     * List drivers with filters and pagination.
     */
    public function index(DriverIndexQueryData $query): Response
    {
        Gate::authorize('viewAny', Driver::class);

        return Inertia::render('User/Drivers/Index/Index', [
            'drivers' => $this->drivers->listPaginated($query),
            'options' => [
                'companies' => $this->companyOptions(),
            ],
            'query' => $query,
            // Distinguishes "table intrinsically empty" from "filter returns 0"
            // to keep the placeholder stable while filters change.
            'hasAnyDriver' => $this->driverRead->existsAny(),
        ]);
    }

    /**
     * Render the driver detail page.
     */
    public function show(Driver $driver): Response
    {
        Gate::authorize('view', $driver);

        $detail = $this->drivers->detail($driver->id);

        if ($detail === null) {
            throw DriverNotFoundException::byId($driver->id);
        }

        return Inertia::render('User/Drivers/Show/Index', [
            'driver' => $detail,
            'options' => [
                'companies' => $this->companyOptions(),
            ],
        ]);
    }

    /**
     * Render the driver creation form.
     */
    public function create(): Response
    {
        Gate::authorize('create', Driver::class);

        return Inertia::render('User/Drivers/Create/Index', [
            'companies' => $this->companyOptions(),
        ]);
    }

    /**
     * Persist a new driver.
     */
    public function store(StoreDriverData $data, CreateDriverAction $action): RedirectResponse
    {
        Gate::authorize('create', Driver::class);

        $driver = $action->execute($data);

        return redirect()
            ->route('user.drivers.show', $driver)
            ->with('toast-success', 'Conducteur créé.');
    }

    /**
     * Render the driver edit form.
     */
    public function edit(Driver $driver): Response
    {
        Gate::authorize('update', $driver);

        return Inertia::render('User/Drivers/Edit/Index', [
            'driver' => [
                'id' => $driver->id,
                'firstName' => $driver->first_name,
                'lastName' => $driver->last_name,
            ],
        ]);
    }

    /**
     * Update an existing driver.
     */
    public function update(Driver $driver, UpdateDriverData $data, UpdateDriverAction $action): RedirectResponse
    {
        Gate::authorize('update', $driver);

        $action->execute($driver, $data);

        return redirect()
            ->route('user.drivers.show', $driver)
            ->with('toast-success', 'Conducteur mis à jour.');
    }

    /**
     * Soft-delete a driver.
     */
    public function destroy(Driver $driver, SoftDeleteDriverAction $action): RedirectResponse
    {
        Gate::authorize('delete', $driver);

        try {
            $action->execute($driver);
        } catch (DriverDeletionBlockedException $e) {
            return back()->with('toast-error', $e->getUserMessage());
        }

        return redirect()
            ->route('user.drivers.index')
            ->with('toast-success', 'Conducteur supprimé.');
    }

    /**
     * JSON list of drivers active in a company on the requested period.
     */
    public function contractOptions(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Driver::class);

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
