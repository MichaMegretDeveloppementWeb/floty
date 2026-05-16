<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Planning;

use App\Actions\Contract\BulkCreateContractsAction;
use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Data\Shared\YearScopeData;
use App\Data\User\Contract\BulkStoreContractsData;
use App\Data\User\Planning\PreviewTaxesInputData;
use App\Data\User\Planning\WeekQueryData;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contract;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Planning\PlanningHeatmapService;
use App\Services\Planning\WeekDetailService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Planning - vue d'ensemble heatmap annuelle (CDC § 3.3) +
 * détail semaine (drawer) + preview taxes + création de contrats.
 *
 * **Refonte 04.F (ADR-0014)** : `storeBulk` crée désormais des contrats
 * (plage `[start_date, end_date]`) au lieu de jours individuels.
 *
 * **Chantier J (ADR-0023)** : sélecteur d'année **local** à la page ·
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
        private readonly CompanyReadRepositoryInterface $companies,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('view-planning');

        $year = $this->resolveYear($request);

        return Inertia::render(
            'User/Planning/Index/Index',
            [
                ...$this->heatmap->buildHeatmap($year),
                // Chantier perf Étape 3 (2026-05-17) · split en 2 defer
                // dans 2 groups différents · Inertia v3 fetch les 2 en
                // parallèle au lieu de séquentiel ·
                //   - `fullYearCosts` (group "fast") · cellules « Taxe
                //     pleine » à GAUCHE, cachées (warm ~50 ms)
                //   - `realCosts` (group "slow") · cellules « €XXXX ·
                //     N j » à DROITE, non cachées (~250 ms)
                // L'utilisateur voit 2 vagues d'apparition au lieu d'1
                // longue attente.
                'fullYearCosts' => Inertia::defer(fn () => $this->heatmap->fullYearCostsForVehicles($year), 'fast'),
                'realCosts' => Inertia::defer(fn () => $this->heatmap->realCostsForVehicles($year), 'slow'),
                'selectedYear' => $year,
                'yearScope' => YearScopeData::fromResolver($this->availableYears),
            ],
        );
    }

    /**
     * Point d'entrée racine de la Vue Entreprise (chantier P2). Sans
     * param d'entreprise dans l'URL, on redirige vers la 1ʳᵉ entreprise
     * existante (1er ID croissant) ; si aucune entreprise n'existe, on
     * affiche une page Empty avec lien vers la création d'entreprise.
     *
     * Le `?year=` éventuel est préservé dans la redirection.
     */
    public function companyIndexRoot(Request $request): RedirectResponse|Response
    {
        Gate::authorize('view-planning');

        $first = $this->companies->findAllOrderedByName()->first();

        if ($first === null) {
            return Inertia::render('User/Planning/Company/Empty');
        }

        $params = ['company' => $first->id];
        $rawYear = $request->query('year');
        if (is_numeric($rawYear)) {
            $params['year'] = (int) $rawYear;
        }

        return redirect()->route('user.planning.companies.index', $params);
    }

    /**
     * Vue Entreprise (chantier P1) : variante de la heatmap focalisée
     * sur une entreprise donnée. Cellule chiffre = jours utilisés par
     * l'entreprise ; cellule couleur = taux d'occupation global du
     * véhicule (signal de disponibilité, inchangé par rapport à
     * `index`).
     *
     * Route Model Binding : 404 automatique si l'entreprise est
     * introuvable.
     */
    public function companyIndex(Request $request, Company $company): Response
    {
        Gate::authorize('view-planning');

        $year = $this->resolveYear($request);

        return Inertia::render(
            'User/Planning/Company/Index',
            [
                ...$this->heatmap->buildHeatmapForCompany($year, $company),
                // Cf. doc `index()` · split en 2 defer parallèles ·
                // `fullYearCosts` est indépendant du scope (théorique
                // 100 % usage), `realCosts` est scopé à l'entreprise.
                'fullYearCosts' => Inertia::defer(fn () => $this->heatmap->fullYearCostsForVehicles($year), 'fast'),
                'realCosts' => Inertia::defer(fn () => $this->heatmap->realCostsForVehicles($year, $company->id), 'slow'),
                'selectedYear' => $year,
                'yearScope' => YearScopeData::fromResolver($this->availableYears),
            ],
        );
    }

    /**
     * GET /app/planning/week?vehicleId=X&week=N&year=Y[&companyId=Z]
     *
     * Quand `companyId` est fourni : mode company-locked (chantier P3).
     * Anonymise les contrats des autres entreprises et filtre
     * `companiesOnWeek` pour ne garder que l'entreprise demandée.
     */
    public function week(WeekQueryData $query, Request $request): JsonResponse
    {
        Gate::authorize('view-planning');

        $year = $this->resolveYear($request);

        $payload = $query->companyId !== null
            ? $this->weekDetail->buildWeekForCompany($query->vehicleId, $query->week, $year, $query->companyId)
            : $this->weekDetail->buildWeek($query->vehicleId, $query->week, $year);

        return response()->json($payload);
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
        Gate::authorize('view-planning');

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
        Gate::authorize('create', Contract::class);

        $createdIds = $this->bulkCreateContracts->execute($input);

        return response()->json(['createdIds' => $createdIds]);
    }

    /**
     * Doctrine "données métier ⊥ règles fiscales" (chantier η Phase 5) ·
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
