<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Planning;

use App\Actions\Contract\BulkCreateContractsAction;
use App\Data\User\Contract\BulkStoreContractsData;
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
 * Planning - vue d'ensemble heatmap annuelle (CDC § 3.3) +
 * détail semaine (drawer) + preview taxes + création de contrats.
 *
 * **Refonte 04.F (ADR-0014)** : `storeBulk` crée désormais des contrats
 * (plage `[start_date, end_date]`) au lieu de jours individuels.
 */
final class PlanningController extends Controller
{
    public function __construct(
        private readonly PlanningHeatmapService $heatmap,
        private readonly WeekDetailService $weekDetail,
        private readonly BulkCreateContractsAction $bulkCreateContracts,
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
     * POST /app/planning/contracts - création d'un (ou plusieurs)
     * contrat(s) sur une plage commune `[start_date, end_date]` à
     * partir du wizard d'attribution rapide du planning.
     *
     * @return JsonResponse `{ createdIds: list<int> }`
     */
    public function storeBulk(BulkStoreContractsData $input): JsonResponse
    {
        $createdIds = $this->bulkCreateContracts->execute($input);

        return response()->json(['createdIds' => $createdIds]);
    }
}
