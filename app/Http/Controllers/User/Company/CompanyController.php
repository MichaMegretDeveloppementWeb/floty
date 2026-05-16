<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Company;

use App\Actions\Company\CreateCompanyAction;
use App\Actions\Company\UpdateCompanyAction;
use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Data\Shared\YearScopeData;
use App\Data\User\Company\CompanyIndexQueryData;
use App\Data\User\Company\StoreCompanyData;
use App\Data\User\Company\UpdateCompanyData;
use App\Data\User\Contract\ContractIndexQueryData;
use App\Exceptions\Company\CompanyShortCodeCollisionException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Billing\PendingInvoicesResolver;
use App\Services\Company\CompanyAggregatesService;
use App\Services\Company\CompanyDetailService;
use App\Services\Company\CompanyListingService;
use App\Services\Contract\ContractQueryService;
use App\Services\Driver\DriverQueryService;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Fiscal\Declaration\DeclarationLifecycleResolver;
use App\Services\Fiscal\Declaration\PendingDeclarationsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyDetailService $companyDetail,
        private readonly CompanyAggregatesService $companyAggregates,
        private readonly CompanyListingService $companyListing,
        private readonly CompanyReadRepositoryInterface $companyRead,
        private readonly DriverQueryService $drivers,
        private readonly ContractQueryService $contracts,
        private readonly CreateCompanyAction $createCompany,
        private readonly UpdateCompanyAction $updateCompany,
        private readonly AvailableYearsResolver $availableYears,
        private readonly PendingDeclarationsResolver $pendingDeclarations,
        private readonly PendingInvoicesResolver $pendingInvoices,
        private readonly DeclarationLifecycleResolver $declarationLifecycle,
    ) {}

    public function index(CompanyIndexQueryData $query): Response
    {
        Gate::authorize('viewAny', Company::class);

        // Sélecteur année **local** à la page (chantier η Phase 3) ·
        // bornes alimentées par `AvailableYearsResolver` (scope global
        // dynamique calculé depuis les contrats, pas la config statique
        // morte). `?year=` URL validé contre ce scope, fallback
        // `currentYear` si invalide.
        $year = $this->resolveSelectedYear($query->year);

        // P0.1 (audit perf 2026-05-16) · liste SLIM en payload initial
        // (sans `annualTaxDue` ni `rentalPriceTotal` qui demandent le
        // pipeline fiscal × N items) · les couts arrivent dans une 2e
        // requete `Inertia::defer` apres le mount, le frontend rend un
        // skeleton sur 2 cellules entre-temps. Gain mesure ~250-375 ms
        // cold sur 25 items.
        $companies = $this->companyListing->listPaginatedSlim($query, $year);
        $companyIds = array_map(static fn ($c): int => $c->id, $companies->data);

        return Inertia::render('User/Companies/Index/Index', [
            'companies' => $companies,
            // P0.1 (audit perf 2026-05-16) · pipeline fiscal +
            // rental calculator (~250-375 ms cold sur 25 items) servis
            // en `Inertia::defer`. Mount immediat + watcher
            // `router.reload` cote front pour re-fetch sur change
            // filtre/tri/page (cf. feedback_inertia_defer_with_partial_reload).
            'costs' => Inertia::defer(
                fn () => $this->companyListing->costsForCompanyIds($companyIds, $year),
            ),
            'query' => $query,
            'selectedYear' => $year,
            'yearScope' => YearScopeData::fromResolver($this->availableYears),
            // Cf. note d'archi sur le bug placeholder : `hasAnyCompany`
            // distingue « table intrinsèquement vide » du « filtre actif
            // retournant 0 » sans dériver depuis 3 sources désynchronisées.
            'hasAnyCompany' => $this->companyRead->existsAny(),
        ]);
    }

    /**
     * Doctrine temporelle (chantier η Phase 3) · résolution `?year=`
     * URL contre le scope global dynamique, fallback `currentYear` si
     * invalide ou absent.
     */
    private function resolveSelectedYear(?int $requested): int
    {
        if ($requested !== null && in_array($requested, $this->availableYears->availableYears(), true)) {
            return $requested;
        }

        return $this->availableYears->currentYear();
    }

    public function show(Company $company, ContractIndexQueryData $contractsQuery, Request $request): Response
    {
        Gate::authorize('view', $company);

        $detail = $this->companyDetail->detail($company->id);

        if ($detail === null) {
            throw new NotFoundHttpException('Entreprise introuvable.');
        }

        // D5.10.V · onglets à chargement lazy + cumulatif. Lit `?tab=`
        // pour décider quelles props sont eager au mount initial · les
        // autres onglets passent par `Inertia::optional()` et ne tirent
        // leur SQL QUE lors d'un partial reload déclenché côté front
        // par `useCompanyTabs` au moment où l'utilisateur clique
        // l'onglet pour la première fois de la session.
        $activeTab = (string) $request->query('tab', 'overview');

        // Onglet Contrats · default année réelle courante au mount quand
        // aucun paramètre période explicite (cohérence avec onglet
        // Fiscalité, ADR-0020 D3). D5.10.U · ne pose QUE `year` ·
        // backend dérive periodStart/End via `effectivePeriod()` ·
        // l'UI distingue ainsi « mode année » vs « plage custom » sans
        // ambiguité.
        $hasExplicitPeriod = $request->exists('year')
            || $request->exists('periodStart')
            || $request->exists('periodEnd');

        if (! $hasExplicitPeriod) {
            $contractsQuery->year = $detail->currentRealYear;
        }

        // D5.10.U · param URL **unifié** `?year=` partagé entre les
        // onglets Fiscalité et Facturation.
        $selectedYear = (int) $request->query('year', (string) $detail->currentRealYear);
        $companyId = $company->id;

        return Inertia::render('User/Companies/Show/Index', [
            // Eager · props partagées (Vue d'ensemble + dots TabsNav +
            // alertes « À faire » + état URL contrats + pills années
            // partagées Billing/Contracts).
            'company' => $detail,
            'contractsQuery' => $contractsQuery,
            'billingYear' => $selectedYear,
            'pendingDeclarations' => $this->pendingDeclarations->pendingForCompany($companyId),
            'pendingInvoices' => $this->pendingInvoices->pendingForCompany($companyId),
            // Plage continue d'années · partagée entre les pills Billing
            // (CompanyBillingTab) et Contracts (CompanyContractsTab) ·
            // 1 SQL très léger (min year), garder en eager.
            'contractsAvailableYears' => $this->contracts->availableYearsRangeForCompany(
                $companyId,
                $detail->currentRealYear,
            ),

            // Onglet "contracts" · table paginée + stats.
            'contracts' => $this->eagerForTab(
                $activeTab === 'contracts',
                fn () => $this->contracts->listPaginatedForCompany($companyId, $contractsQuery),
            ),
            'contractsStats' => $this->eagerForTab(
                $activeTab === 'contracts',
                fn () => $this->contracts->statsForCompany(
                    $companyId,
                    // D5.10.U · `effectivePeriod()` retombe sur l'exercice
                    // dérivé de `year` quand pas de plage custom · cohérent
                    // avec le filtrage SQL du listing contrats.
                    $contractsQuery->effectivePeriod()['periodStart'],
                    $contractsQuery->effectivePeriod()['periodEnd'],
                ),
            ),

            // Onglet "drivers" · liste plate pour picker AddCompanyDriverModal.
            'options' => [
                'drivers' => $this->eagerForTab(
                    $activeTab === 'drivers',
                    fn () => $this->drivers->listForOptions(),
                ),
            ],

            // Onglet "fiscal" · breakdown par véhicule + cycle de vie déclaration.
            'companyFiscal' => $this->eagerForTab(
                $activeTab === 'fiscal',
                fn () => $this->companyAggregates->fiscalBreakdownForYear($companyId, $selectedYear),
            ),
            'declarationLifecycle' => $this->eagerForTab(
                $activeTab === 'fiscal',
                fn () => $this->declarationLifecycle->resolveForCompanyYear($companyId, $selectedYear),
            ),

            // Onglet "billing" · récap mensuel.
            'companyBilling' => $this->eagerForTab(
                $activeTab === 'billing',
                fn () => $this->companyAggregates->billingForYear($companyId, $selectedYear),
            ),
        ]);
    }

    /**
     * D5.10.V · Helper pour le chargement lazy + cumulatif des onglets.
     * Retourne la valeur immédiatement quand l'onglet ciblé est l'onglet
     * actif au mount (props eager), sinon retourne un `OptionalProp` qui
     * ne s'exécute QUE sur partial reload `only: [...]`.
     */
    private function eagerForTab(bool $isActive, callable $resolver): mixed
    {
        return $isActive ? $resolver() : Inertia::optional($resolver);
    }

    public function create(): Response
    {
        Gate::authorize('create', Company::class);

        return Inertia::render('User/Companies/Create/Index', [
            'colors' => $this->companyListing->colorOptions(),
        ]);
    }

    public function store(StoreCompanyData $data): RedirectResponse
    {
        Gate::authorize('create', Company::class);

        try {
            $this->createCompany->execute($data);
        } catch (CompanyShortCodeCollisionException $e) {
            throw ValidationException::withMessages([
                'legal_name' => $e->getUserMessage(),
            ]);
        }

        return redirect()
            ->route('user.companies.index')
            ->with('toast-success', 'Entreprise créée.');
    }

    public function edit(Company $company): Response
    {
        Gate::authorize('update', $company);

        // Slim DTO sans pipeline fiscal · le formulaire Edit n'affiche
        // que 14 champs scalaires d'identité, pas besoin de drivers /
        // lifetime / history / activityByYear (gain ~280 ms cold).
        $detail = $this->companyDetail->detailForEdit($company->id);

        if ($detail === null) {
            throw new NotFoundHttpException('Entreprise introuvable.');
        }

        return Inertia::render('User/Companies/Edit/Index', [
            'company' => $detail,
            'colors' => $this->companyListing->colorOptions(),
        ]);
    }

    public function update(Company $company, UpdateCompanyData $data): RedirectResponse
    {
        Gate::authorize('update', $company);

        $this->updateCompany->execute($company->id, $data);

        return redirect()
            ->route('user.companies.show', ['company' => $company->id])
            ->with('toast-success', 'Entreprise mise à jour.');
    }
}
