<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Driver;

use App\Actions\Driver\AddDriverCompanyMembershipAction;
use App\Actions\Driver\DetachDriverCompanyMembershipAction;
use App\Actions\Driver\LeaveDriverCompanyMembershipAction;
use App\Actions\Driver\UpdateDriverCompanyMembershipAction;
use App\Data\User\Driver\AddDriverCompanyMembershipData;
use App\Data\User\Driver\LeaveDriverCompanyMembershipData;
use App\Data\User\Driver\UpdateDriverCompanyMembershipData;
use App\Exceptions\Driver\DriverCompanyMembershipBlockedException;
use App\Exceptions\Driver\DriverMembershipNotFoundException;
use App\Exceptions\Driver\LeaveResolutionInvalidException;
use App\Exceptions\Driver\MembershipChronologyException;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Services\Driver\DriverQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Driver × Company membership endpoints.
 */
final class DriverMembershipController extends Controller
{
    public function __construct(
        private readonly DriverQueryService $drivers,
    ) {}

    /**
     * Attach a driver to a company.
     */
    public function attachCompany(
        Driver $driver,
        AddDriverCompanyMembershipData $data,
        AddDriverCompanyMembershipAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $driver);

        $action->execute($driver, $data);

        return back()->with('toast-success', 'Conducteur ajouté à l\'entreprise.');
    }

    /**
     * Record a driver leaving a company, resolving any upcoming contracts.
     */
    public function leaveCompany(
        Driver $driver,
        int $companyId,
        LeaveDriverCompanyMembershipData $data,
        LeaveDriverCompanyMembershipAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $driver);

        try {
            $action->execute($driver, $companyId, $data);
        } catch (DriverMembershipNotFoundException $e) {
            return back()->with('toast-error', $e->getUserMessage());
        } catch (LeaveResolutionInvalidException $e) {
            throw ValidationException::withMessages(['future_contracts_resolution' => [$e->getUserMessage()]]);
        }

        return back()->with('toast-success', 'Sortie enregistrée.');
    }

    /**
     * Detach a driver membership by pivot id.
     */
    public function detachCompany(
        Driver $driver,
        int $pivotId,
        DetachDriverCompanyMembershipAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $driver);

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
     * Update a membership's joined_at date.
     */
    public function updateMembership(
        Driver $driver,
        int $pivotId,
        UpdateDriverCompanyMembershipData $data,
        UpdateDriverCompanyMembershipAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $driver);

        try {
            $action->execute($pivotId, $data);
        } catch (DriverMembershipNotFoundException $e) {
            return back()->with('toast-error', $e->getUserMessage());
        } catch (MembershipChronologyException $e) {
            throw ValidationException::withMessages(['joined_at' => [$e->getUserMessage()]]);
        }

        return back()->with('toast-success', 'Rattachement mis à jour.');
    }

    /**
     * JSON preview for the leave modal: upcoming contracts and eligible replacements.
     *
     * For the chosen leftAt date, returns the driver's future contracts in
     * the company together with eligible replacement drivers (active on the
     * exact period). The leaving driver is excluded from candidates.
     */
    public function futureContractsForLeave(
        Driver $driver,
        int $companyId,
        Request $request,
    ): JsonResponse {
        Gate::authorize('view', $driver);

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
}
