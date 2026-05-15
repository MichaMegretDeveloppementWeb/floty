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
 * Endpoints membership Driver × Company (extrait de `DriverController`
 * pour respecter R7/R10 ADR-0013 · Lot 4 D13 / F-34-105).
 *
 * Regroupe le workflow d'attachement/sortie/édition d'un conducteur
 * dans une entreprise + l'endpoint JSON consommé par la modal de
 * sortie pour pré-calculer les contrats à venir + les remplaçants
 * éligibles ·
 *   - `attachCompany`           POST   ajoute un rattachement
 *   - `leaveCompany`            PATCH  sortie (avec résolution des
 *                                       contrats à venir · workflow Q6)
 *   - `detachCompany`           DELETE supprime un rattachement
 *   - `updateMembership`        PATCH  édite `joined_at` (chantier B)
 *   - `futureContractsForLeave` GET    JSON · contrats + remplaçants
 *                                       pour la modal de sortie
 */
final class DriverMembershipController extends Controller
{
    public function __construct(
        private readonly DriverQueryService $drivers,
    ) {}

    public function attachCompany(
        Driver $driver,
        AddDriverCompanyMembershipData $data,
        AddDriverCompanyMembershipAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $driver);

        $action->execute($driver, $data);

        return back()->with('toast-success', 'Conducteur ajouté à l\'entreprise.');
    }

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
     * PATCH /drivers/{driver}/memberships/{pivotId} · édite une membership
     * existante (chantier B). Scope V1 · `joined_at` uniquement (la
     * gestion de `left_at` reste pilotée par le workflow Sortir).
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
