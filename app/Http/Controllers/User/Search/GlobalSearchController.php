<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Search;

use App\Data\User\Search\GlobalSearchQueryData;
use App\Http\Controllers\Controller;
use App\Services\Search\GlobalSearchService;
use Illuminate\Http\JsonResponse;

/**
 * AJAX JSON endpoint backing the global ⌘K search palette.
 *
 * Auth is enforced at the route group level; the route is throttled.
 */
final class GlobalSearchController extends Controller
{
    public function __construct(
        private readonly GlobalSearchService $search,
    ) {}

    /**
     * Run a global search and return grouped results.
     */
    public function __invoke(GlobalSearchQueryData $query): JsonResponse
    {
        return response()->json($this->search->searchAll($query->q));
    }
}
