<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Vehicle;

use App\Exceptions\VehicleRegistryLookup\RegistryLookupRateLimitedException;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupTimeoutException;
use App\Exceptions\VehicleRegistryLookup\RegistryLookupUnavailableException;
use App\Exceptions\VehicleRegistryLookup\VehicleNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Strategies\VehicleRegistryLookup\VehicleRegistryLookupStrategyFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Endpoint JSON appelé par le bouton « Pré-remplir depuis la carte
 * grise » du formulaire Create/Edit véhicule.
 *
 * Garde-fous (en plus du throttle de la route) :
 *   - Gate `create` sur {@see Vehicle} · seul un user habilité à
 *     créer un véhicule peut consommer l'API tiers (coût + RGPD).
 *   - Gate `isAvailable()` côté factory · double sécurité même si le
 *     bouton Vue est manquant, le back refuse explicitement quand
 *     aucune strategy n'est implémentée.
 *
 * Toutes les exceptions métier sont attrapées et transformées en
 * réponse JSON typée avec code HTTP cohérent et message utilisateur
 * français (via {@see App\Exceptions\BaseAppException::getUserMessage()}).
 */
final class VehicleRegistryLookupController extends Controller
{
    public function __construct(
        private readonly VehicleRegistryLookupStrategyFactory $factory,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('create', Vehicle::class);

        if (! $this->factory->isAvailable()) {
            return $this->jsonError(
                RegistryLookupUnavailableException::featureDisabled(),
                HttpResponse::HTTP_SERVICE_UNAVAILABLE,
                'unavailable',
            );
        }

        $validated = $request->validate([
            // Plaque française · longueur min 4 chars (formats anciens
            // FNI 2-3 chiffres + lettres) jusqu'à 20 chars (largeur de
            // colonne en base + tirets de présentation). Validation
            // sémantique fine déléguée au provider, ici juste un
            // garde-fou anti-spam.
            'license_plate' => ['required', 'string', 'min:4', 'max:20'],
        ]);

        try {
            $strategy = $this->factory->make();
            $result = $strategy->lookup($validated['license_plate']);
        } catch (VehicleNotFoundException $e) {
            return $this->jsonError($e, HttpResponse::HTTP_NOT_FOUND, 'not_found');
        } catch (RegistryLookupTimeoutException $e) {
            return $this->jsonError($e, HttpResponse::HTTP_GATEWAY_TIMEOUT, 'timeout');
        } catch (RegistryLookupRateLimitedException $e) {
            return $this->jsonError($e, HttpResponse::HTTP_TOO_MANY_REQUESTS, 'rate_limited');
        } catch (RegistryLookupUnavailableException $e) {
            return $this->jsonError($e, HttpResponse::HTTP_SERVICE_UNAVAILABLE, 'unavailable');
        }

        return response()->json($result);
    }

    private function jsonError(
        VehicleNotFoundException|RegistryLookupTimeoutException|RegistryLookupRateLimitedException|RegistryLookupUnavailableException $exception,
        int $status,
        string $code,
    ): JsonResponse {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $exception->getUserMessage(),
            ],
        ], $status);
    }
}
