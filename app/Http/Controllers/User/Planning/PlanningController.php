<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Planning;

use App\Actions\Contract\BulkCreateContractsAction;
use App\Data\Shared\YearScopeData;
use App\Data\User\Contract\BulkStoreContractsData;
use App\Data\User\Planning\PreviewTaxesInputData;
use App\Data\User\Planning\WeekQueryData;
use App\Http\Controllers\Controller;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Planning\PlanningHeatmapService;
use App\Services\Planning\WeekDetailService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Planning - vue d'ensemble heatmap annuelle (CDC § 3.3) +
 * détail semaine (drawer) + preview taxes + création de contrats.
 *
 * **Refonte 04.F (ADR-0014)** : `storeBulk` crée désormais des contrats
 * (plage `[start_date, end_date]`) au lieu de jours individuels.
 *
 * **Chantier J (ADR-0020)** : sélecteur d'année **local** à la page —
 * `?year=YYYY` URL avec fallback année calendaire courante. Plus de
 * dépendance à `FiscalYearResolver` (supprimé).
 */
final class PlanningController extends Controller
{
    public function __construct(
        private readonly PlanningHeatmapService $heatmap,
        private readonly WeekDetailService $weekDetail,
        private readonly BulkCreateContractsAction $bulkCreateContracts,
        private readonly AvailableYearsResolver $availableYears,
    ) {}

    public function index(Request $request): Response
    {
        $year = $this->resolveYear($request);

        return Inertia::render(
            'User/Planning/Index/Index',
            [
                ...$this->heatmap->buildHeatmap($year),
                'selectedYear' => $year,
                'yearScope' => YearScopeData::fromResolver($this->availableYears),
            ],
        );
    }

    /**
     * GET /app/planning/week?vehicleId=X&week=N&year=Y
     */
    public function week(WeekQueryData $query, Request $request): JsonResponse
    {
        return response()->json(
            $this->weekDetail->buildWeek($query->vehicleId, $query->week, $this->resolveYear($request)),
        );
    }

    /**
     * POST /app/planning/preview-taxes
     *
     * L'année est dérivée du query param `?year=` ou de la première
     * date du payload (les dates partagent toujours la même année dans
     * le wizard d'attribution).
     */
    public function previewTaxes(PreviewTaxesInputData $input, Request $request): JsonResponse
    {
        $year = $request->query('year') !== null
            ? $this->resolveYear($request)
            : (int) CarbonImmutable::parse($input->dates[0])->year;

        return response()->json(
            $this->weekDetail->previewTaxes($input, $year),
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

    /**
     * Doctrine "données métier ⊥ règles fiscales" (chantier η Phase 5) —
     * l'utilisateur pilote n'importe quelle année calendaire raisonnable
     * (range 1900-2100). Le sélecteur UI affiche `yearScope` (scope
     * contrats), mais un deep-link `?year=` libre reste honoré. Fallback
     * année calendaire courante.
     */
    private function resolveYear(Request $request): int
    {
        $raw = $request->query('year');
        $candidate = is_numeric($raw) ? (int) $raw : null;

        if ($candidate !== null && $candidate >= 1900 && $candidate <= 2100) {
            return $candidate;
        }

        return $this->availableYears->currentYear();
    }
}
