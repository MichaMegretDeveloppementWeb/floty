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

final class VehicleRegistryLookupController extends Controller
{
    public function __construct(
        private readonly VehicleRegistryLookupStrategyFactory $factory,
    ) {}

    /**
     * Handle a license plate lookup request and return the resolved vehicle data.
     */
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

    /**
     * Render a typed JSON error payload from a domain exception.
     */
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
