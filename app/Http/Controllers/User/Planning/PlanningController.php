<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Planning;

use App\Data\User\Planning\BulkCreateAssignmentsInputData;
use App\Data\User\Planning\PreviewTaxesInputData;
use App\Http\Controllers\Controller;
use App\Services\Assignment\AssignmentQueryService;
use App\Services\Planning\PlanningHeatmapService;
use App\Services\Planning\WeekDetailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Planning — vue d'ensemble heatmap annuelle (CDC § 3.3) +
 * détail semaine (drawer) + preview taxes + création en masse.
 */
final class PlanningController extends Controller
{
    public function __construct(
        private readonly PlanningHeatmapService $heatmap,
        private readonly WeekDetailService $weekDetail,
        private readonly AssignmentQueryService $assignments,
    ) {}

    public function index(): Response
    {
        return Inertia::render(
            'User/Planning/Index',
            $this->heatmap->buildHeatmap((int) config('floty.fiscal.current_year')),
        );
    }

    /**
     * GET /app/planning/week?vehicleId=X&week=N
     */
    public function week(Request $request): JsonResponse
    {
        $vehicleId = (int) $request->query('vehicleId');
        $weekNumber = (int) $request->query('week');

        if ($vehicleId <= 0 || $weekNumber < 1 || $weekNumber > 53) {
            abort(400, 'Paramètres vehicleId et week requis.');
        }

        return response()->json(
            $this->weekDetail->buildWeek(
                $vehicleId,
                $weekNumber,
                (int) config('floty.fiscal.current_year'),
            ),
        );
    }

    /**
     * POST /app/planning/preview-taxes
     */
    public function previewTaxes(PreviewTaxesInputData $input): JsonResponse
    {
        return response()->json(
            $this->weekDetail->previewTaxes(
                $input,
                (int) config('floty.fiscal.current_year'),
            ),
        );
    }

    /**
     * POST /app/planning/assignments
     */
    public function storeBulk(BulkCreateAssignmentsInputData $input): JsonResponse
    {
        return response()->json(
            $this->assignments->createBulk(
                $input->vehicleId,
                $input->companyId,
                $input->dates,
            ),
        );
    }
}
