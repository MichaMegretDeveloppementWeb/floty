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
 * Composer une réponse de redirection conviviale pour les exceptions HTTP
 * qui aboutiraient sinon à une page d'erreur Inertia.
 *
 * Doctrine UX Floty : préférer un redirect transparent vers une page
 * utile (index du domaine concerné, ou dashboard en fallback) + un toast
 * explicite, plutôt qu'une page d'erreur 404/403 isolée. Voir
 * `bootstrap/app.php` pour le câblage des render handlers.
 *
 * Limites : seules les requêtes HTML (pas `expectsJson()`) doivent
 * passer par ce renderer. Le câblage d'appel s'en assure côté
 * `bootstrap/app.php`.
 */
final class UserFacingExceptionRenderer
{
    /**
     * Mapping explicite des Models de domaine vers leur route d'index +
     * label utilisateur en français. Les entités sans index propre
     * tombent sur le parent contextuel le plus pertinent (ex. les tarifs
     * véhicule renvoient vers la liste véhicules).
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
        Unavailability::class => ['route' => 'user.vehicles.index', 'label' => 'cette indisponibilité'],
        VehicleYearlyPricing::class => ['route' => 'user.vehicles.index', 'label' => 'ce tarif'],
        VehicleFiscalCharacteristics::class => ['route' => 'user.vehicles.index', 'label' => 'cette caractéristique fiscale'],
        ContractDocument::class => ['route' => 'user.contracts.index', 'label' => 'ce document'],
        FiscalRule::class => ['route' => 'user.fiscal-rules.index', 'label' => 'cette règle fiscale'],
        BillingSettings::class => ['route' => 'user.dashboard', 'label' => 'ces paramètres'],
    ];

    /**
     * Fallback générique quand le Model n'est pas mappé (ex. nouvelle
     * entité ajoutée sans déclaration ici, ou Models hors domaine
     * utilisateur comme User qui ne devrait pas atteindre ce renderer).
     */
    private const FALLBACK = ['route' => 'user.dashboard', 'label' => 'cet élément'];

    /**
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

    public static function renderAuthorization(AuthorizationException $e): RedirectResponse
    {
        return redirect()
            ->route('user.dashboard')
            ->with('toast-error', "Vous n'avez pas accès à cet élément.");
    }

    public static function renderNotFoundHttp(NotFoundHttpException $e): RedirectResponse
    {
        return redirect()
            ->route('user.dashboard')
            ->with('toast-error', "Cette page n'existe pas ou a été supprimée.");
    }
}
