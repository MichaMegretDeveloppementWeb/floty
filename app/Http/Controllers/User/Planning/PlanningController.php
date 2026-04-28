<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Planning;

use App\Actions\Assignment\BulkCreateAssignmentsAction;
use App\Data\User\Planning\BulkCreateAssignmentsInputData;
use App\Data\User\Planning\PreviewTaxesInputData;
use App\Data\User\Planning\WeekQueryData;
use App\Fiscal\Resolver\FiscalYearResolver;
use App\Http\Controllers\Controller;
use App\Services\Planning\PlanningHeatmapService;
use App\Services\Planning\WeekDetailService;
use Illuminate\Http\JsonResponse;
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
        private readonly BulkCreateAssignmentsAction $bulkCreate,
        private readonly FiscalYearResolver $fiscalYear,
    ) {}

    public function index(): Response
    {
        return Inertia::render(
            'User/Planning/Index/Index',
            $this->heatmap->buildHeatmap($this->fiscalYear->resolve()),
        );
    }

    /**
     * GET /app/planning/week?vehicleId=X&week=N
     */
    public function week(WeekQueryData $query): JsonResponse
    {
        return response()->json(
            $this->weekDetail->buildWeek($query->vehicleId, $query->week, $this->fiscalYear->resolve()),
        );
    }

    /**
     * POST /app/planning/preview-taxes
     */
    public function previewTaxes(PreviewTaxesInputData $input): JsonResponse
    {
        return response()->json(
            $this->weekDetail->previewTaxes($input, $this->fiscalYear->resolve()),
        );
    }

    /**
     * POST /app/planning/assignments
     */
    public function storeBulk(BulkCreateAssignmentsInputData $input): JsonResponse
    {
        return response()->json(
            $this->bulkCreate->execute($input->vehicleId, $input->companyId, $input->dates),
        );
    }
}
