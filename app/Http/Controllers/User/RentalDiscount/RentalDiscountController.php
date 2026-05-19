<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\RentalDiscount;

use App\Actions\RentalDiscount\CreateRentalDiscountAction;
use App\Actions\RentalDiscount\DeleteRentalDiscountAction;
use App\Actions\RentalDiscount\UpdateRentalDiscountAction;
use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\RentalDiscount\RentalDiscountIndexQueryData;
use App\Data\User\RentalDiscount\StoreRentalDiscountData;
use App\Data\User\RentalDiscount\UpdateRentalDiscountData;
use App\Exceptions\RentalDiscount\RentalDiscountOverlapException;
use App\Http\Controllers\Controller;
use App\Models\RentalDiscount;
use App\Services\RentalDiscount\RentalDiscountConflictService;
use App\Services\RentalDiscount\RentalDiscountQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Rental discount CRUD plus a live conflict-check JSON endpoint.
 */
final class RentalDiscountController extends Controller
{
    public function __construct(
        private readonly RentalDiscountQueryService $query,
        private readonly RentalDiscountReadRepositoryInterface $reader,
        private readonly CompanyReadRepositoryInterface $companyRead,
        private readonly VehicleReadRepositoryInterface $vehicleRead,
        private readonly RentalDiscountConflictService $conflicts,
    ) {}

    /**
     * List rental discounts with filters and aggregate stats.
     */
    public function index(RentalDiscountIndexQueryData $query): Response
    {
        Gate::authorize('viewAny', RentalDiscount::class);

        return Inertia::render('User/RentalDiscounts/Index/Index', [
            'rentalDiscounts' => $this->query->listPaginated($query),
            'options' => [
                'companies' => $this->companyOptions(),
            ],
            'stats' => $this->query->statsForIndex(),
            'query' => $query,
            'hasAnyRentalDiscount' => $this->reader->existsAny(),
        ]);
    }

    /**
     * Render the rental discount detail page.
     */
    public function show(RentalDiscount $rentalDiscount): Response
    {
        Gate::authorize('view', $rentalDiscount);

        $detail = $this->query->detail($rentalDiscount->id);

        if ($detail === null) {
            throw new NotFoundHttpException('Réduction commerciale introuvable.');
        }

        return Inertia::render('User/RentalDiscounts/Show/Index', [
            'rentalDiscount' => $detail,
        ]);
    }

    /**
     * Render the rental discount creation form.
     */
    public function create(): Response
    {
        Gate::authorize('create', RentalDiscount::class);

        return Inertia::render('User/RentalDiscounts/Create/Index', [
            'companies' => $this->companyOptions(),
            'vehicles' => $this->vehicleOptions(),
        ]);
    }

    /**
     * Persist a new rental discount.
     */
    public function store(StoreRentalDiscountData $data, CreateRentalDiscountAction $action): RedirectResponse
    {
        Gate::authorize('create', RentalDiscount::class);

        try {
            $discount = $action->execute($data, (int) (Auth::id() ?? 0));
        } catch (RentalDiscountOverlapException $e) {
            throw ValidationException::withMessages([
                'start_date' => $e->getUserMessage(),
            ]);
        }

        return redirect()
            ->route('user.rental-discounts.show', $discount)
            ->with('toast-success', 'Réduction commerciale créée.');
    }

    /**
     * Render the rental discount edit form.
     */
    public function edit(RentalDiscount $rentalDiscount): Response
    {
        Gate::authorize('update', $rentalDiscount);

        $detail = $this->query->detail($rentalDiscount->id);

        if ($detail === null) {
            throw new NotFoundHttpException('Réduction commerciale introuvable.');
        }

        return Inertia::render('User/RentalDiscounts/Edit/Index', [
            'rentalDiscount' => $detail,
            'vehicles' => $this->vehicleOptions(),
        ]);
    }

    /**
     * Update an existing rental discount.
     */
    public function update(
        RentalDiscount $rentalDiscount,
        UpdateRentalDiscountData $data,
        UpdateRentalDiscountAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $rentalDiscount);

        try {
            $action->execute($rentalDiscount, $data);
        } catch (RentalDiscountOverlapException $e) {
            throw ValidationException::withMessages([
                'start_date' => $e->getUserMessage(),
            ]);
        }

        return redirect()
            ->route('user.rental-discounts.show', $rentalDiscount)
            ->with('toast-success', 'Réduction commerciale mise à jour.');
    }

    /**
     * Archive a rental discount.
     */
    public function destroy(RentalDiscount $rentalDiscount, DeleteRentalDiscountAction $action): RedirectResponse
    {
        Gate::authorize('delete', $rentalDiscount);

        $action->execute($rentalDiscount);

        return redirect()
            ->route('user.rental-discounts.index')
            ->with('toast-success', 'Réduction commerciale archivée.');
    }

    /**
     * JSON endpoint listing rental discounts that overlap the requested period.
     *
     * Payload:
     *   - `company_id` (int, required)
     *   - `start_date` (Y-m-d, required)
     *   - `end_date` (Y-m-d, required, >= start_date)
     *   - `vehicle_ids` (array<int>, optional; empty = "all vehicles")
     *   - `exclude_id` (int, optional; id of the discount being edited)
     */
    public function checkConflicts(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', RentalDiscount::class);

        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'vehicle_ids' => ['nullable', 'array'],
            'vehicle_ids.*' => ['integer', 'exists:vehicles,id'],
            'exclude_id' => ['nullable', 'integer', 'exists:rental_discounts,id'],
        ]);

        $vehicleIds = array_values(array_unique(array_map(
            static fn ($v): int => (int) $v,
            $validated['vehicle_ids'] ?? [],
        )));

        $conflicts = $this->conflicts->findOverlapping(
            companyId: (int) $validated['company_id'],
            startDate: (string) $validated['start_date'],
            endDate: (string) $validated['end_date'],
            vehicleIds: $vehicleIds,
            excludeId: isset($validated['exclude_id']) ? (int) $validated['exclude_id'] : null,
        );

        return response()->json([
            'hasConflicts' => $conflicts !== [],
            'conflicts' => array_map(static fn (RentalDiscount $d): array => [
                'id' => $d->id,
                'label' => $d->label,
                'startDate' => $d->start_date->toDateString(),
                'endDate' => $d->end_date->toDateString(),
                'discountBasisPoints' => $d->discount_basis_points,
                'vehiclesCount' => $d->vehicles->count(),
                'isAllVehicles' => $d->vehicles->count() === 0,
            ], $conflicts),
        ]);
    }

    /**
     * @return array<int, array{id: int, shortCode: string, legalName: string, color: string}>
     */
    private function companyOptions(): array
    {
        return $this->companyRead
            ->findAllForOptions()
            ->map(fn ($c): array => [
                'id' => $c->id,
                'shortCode' => $c->short_code,
                'legalName' => $c->legal_name,
                'color' => $c->color->value,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, licensePlate: string, brand: string, model: string}>
     */
    private function vehicleOptions(): array
    {
        return $this->vehicleRead
            ->findAllForOptions()
            ->map(fn ($v): array => [
                'id' => $v->id,
                'licensePlate' => $v->license_plate,
                'brand' => $v->brand,
                'model' => $v->model,
            ])
            ->all();
    }
}
