<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Search;

use App\Data\User\Search\GlobalSearchQueryData;
use App\Http\Controllers\Controller;
use App\Services\Search\GlobalSearchService;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint AJAX JSON de la barre de recherche globale (palette ⌘K, V1.1).
 *
 * Invocable controller `GET /app/search?q=<query>`. Auth middleware au
 * niveau du groupe (cf. {@see routes/user.php}), throttle 60/min pour
 * limiter les abus (largement suffisant avec debounce 200 ms côté
 * client).
 *
 * Pas de policy custom · Floty est mono-locataire interne, tous les
 * utilisateurs authentifiés voient toutes les entités. Si on ajoute
 * un jour du cloisonnement multi-tenant, la restriction se fera dans
 * {@see GlobalSearchService}.
 */
final class GlobalSearchController extends Controller
{
    public function __construct(
        private readonly GlobalSearchService $search,
    ) {}

    public function __invoke(GlobalSearchQueryData $query): JsonResponse
    {
        return response()->json($this->search->searchAll($query->q));
    }
}
