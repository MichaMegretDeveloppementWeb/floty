<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\BillingSettings;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\Driver;
use App\Models\FiscalRule;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\RentalDiscount;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Models\VehicleYearlyPricing;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Composes friendly redirect responses for HTTP exceptions that would otherwise hit a raw error page.
 *
 * Floty UX doctrine: redirect to a useful index (or dashboard fallback) with a toast,
 * rather than showing an isolated 404/403. Wired in `bootstrap/app.php`. HTML requests only.
 */
final class UserFacingExceptionRenderer
{
    /**
     * Domain model to index route and French user label mapping.
     * Entities without a dedicated index fall back to the closest contextual parent.
     *
     * @var array<class-string, array{route: string, label: string}>
     */
    private const MAPPING = [
        Company::class => ['route' => 'user.companies.index', 'label' => 'cette entreprise'],
        Vehicle::class => ['route' => 'user.vehicles.index', 'label' => 'ce véhicule'],
        Driver::class => ['route' => 'user.drivers.index', 'label' => 'ce conducteur'],
        Contract::class => ['route' => 'user.contracts.index', 'label' => 'ce contrat'],
        Invoice::class => ['route' => 'user.invoices.index', 'label' => 'cette facture'],
        InvoiceLine::class => ['route' => 'user.invoices.index', 'label' => 'cette ligne de facture'],
        RentalDiscount::class => ['route' => 'user.rental-discounts.index', 'label' => 'cette réduction commerciale'],
        Unavailability::class => ['route' => 'user.vehicles.index', 'label' => 'cette indisponibilité'],
        VehicleYearlyPricing::class => ['route' => 'user.vehicles.index', 'label' => 'ce tarif'],
        VehicleFiscalCharacteristics::class => ['route' => 'user.vehicles.index', 'label' => 'cette caractéristique fiscale'],
        ContractDocument::class => ['route' => 'user.contracts.index', 'label' => 'ce document'],
        FiscalRule::class => ['route' => 'user.fiscal-rules.index', 'label' => 'cette règle fiscale'],
        BillingSettings::class => ['route' => 'user.dashboard', 'label' => 'ces paramètres'],
    ];

    /**
     * Generic fallback when the model is not mapped.
     */
    private const FALLBACK = ['route' => 'user.dashboard', 'label' => 'cet élément'];

    /**
     * Render a `ModelNotFoundException` as a redirect with a contextual toast.
     *
     * @param  ModelNotFoundException<Model>  $e
     */
    public static function renderModelNotFound(ModelNotFoundException $e): RedirectResponse
    {
        $modelClass = $e->getModel();
        $config = self::MAPPING[$modelClass] ?? self::FALLBACK;

        return redirect()
            ->route($config['route'])
            ->with(
                'toast-error',
                sprintf("%s n'existe plus ou a été supprimé.", ucfirst((string) $config['label'])),
            );
    }

    /**
     * Render an `AuthorizationException` as a dashboard redirect with a generic toast.
     */
    public static function renderAuthorization(AuthorizationException $e): RedirectResponse
    {
        return redirect()
            ->route('user.dashboard')
            ->with('toast-error', "Vous n'avez pas accès à cet élément.");
    }

    /**
     * Render a `NotFoundHttpException` as a dashboard redirect with a generic toast.
     */
    public static function renderNotFoundHttp(NotFoundHttpException $e): RedirectResponse
    {
        return redirect()
            ->route('user.dashboard')
            ->with('toast-error', "Cette page n'existe pas ou a été supprimée.");
    }
}
