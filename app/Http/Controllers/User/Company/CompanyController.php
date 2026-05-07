<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Company;

use App\Actions\Company\CreateCompanyAction;
use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Data\Shared\YearScopeData;
use App\Data\User\Company\CompanyIndexQueryData;
use App\Data\User\Company\StoreCompanyData;
use App\Data\User\Contract\ContractIndexQueryData;
use App\Data\User\FiscalDeclaration\DeclarationListItemData;
use App\Exceptions\Company\CompanyShortCodeCollisionException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Company\CompanyQueryService;
use App\Services\Contract\ContractQueryService;
use App\Services\Driver\DriverQueryService;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Fiscal\Declaration\PendingDeclarationsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyQueryService $companies,
        private readonly CompanyReadRepositoryInterface $companyRead,
        private readonly DriverQueryService $drivers,
        private readonly ContractQueryService $contracts,
        private readonly CreateCompanyAction $createCompany,
        private readonly AvailableYearsResolver $availableYears,
        private readonly PendingDeclarationsResolver $pendingDeclarations,
        private readonly FiscalDeclarationReadRepositoryInterface $declarations,
    ) {}

    public function index(CompanyIndexQueryData $query): Response
    {
        // Sélecteur année **local** à la page (chantier η Phase 3) —
        // bornes alimentées par `AvailableYearsResolver` (scope global
        // dynamique calculé depuis les contrats, pas la config statique
        // morte). `?year=` URL validé contre ce scope, fallback
        // `currentYear` si invalide.
        $year = $this->resolveSelectedYear($query->year);

        return Inertia::render('User/Companies/Index/Index', [
            'companies' => $this->companies->listPaginated($query, $year),
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
     * Doctrine temporelle (chantier η Phase 3) — résolution `?year=`
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
        $detail = $this->companies->detail($company->id);

        if ($detail === null) {
            throw new NotFoundHttpException('Entreprise introuvable.');
        }

        // Onglet Contrats — default année réelle courante au mount quand
        // aucun paramètre période explicite (cohérence avec onglet
        // Fiscalité, ADR-0020 D3). Liens partagés (`?year=`,
        // `?periodStart=`, `?periodEnd=`) respectés tels quels.
        $hasExplicitPeriod = $request->exists('year')
            || $request->exists('periodStart')
            || $request->exists('periodEnd');

        if (! $hasExplicitPeriod) {
            $contractsQuery->year = $detail->currentRealYear;
            $contractsQuery->periodStart = sprintf('%d-01-01', $detail->currentRealYear);
            $contractsQuery->periodEnd = sprintf('%d-12-31', $detail->currentRealYear);
        }

        // Onglet Fiscalité (chantier N.2) — sélecteur d'année **local**
        // indépendant. Préfixe `?fiscalYear=` pour ne pas collide avec
        // `?year=` (Activité Vue d'ensemble) ni `?periodStart/End=`
        // (Contrats). Default = année réelle courante.
        $fiscalYear = (int) $request->query('fiscalYear', (string) $detail->currentRealYear);

        // Onglet Facturation (Phase 14.D V1.2) — sélecteur d'année
        // **local** indépendant via `?billingYear=`. Mirroir du pattern
        // `?fiscalYear=` pour cohérence UX.
        $billingYear = (int) $request->query('billingYear', (string) $detail->currentRealYear);

        return Inertia::render('User/Companies/Show/Index', [
            'company' => $detail,
            'options' => [
                // Liste plate des drivers pour peupler le picker du modal
                // d'ajout de membership (`AddCompanyDriverModal`). La modale
                // filtre côté front les drivers déjà rattachés à la company.
                'drivers' => $this->drivers->listForOptions(),
            ],
            // Onglet Contrats — table paginée server-side (chantier N.1).
            // Le query DTO standard `ContractIndexQueryData` est consommé
            // directement (filtres `periodStart`/`periodEnd`, `type`,
            // pagination, tri). Le `companyId` est forcé côté service à
            // `$company->id` indépendamment de ce qui pourrait venir de
            // l'URL — la fiche Company impose son propre scope.
            'contracts' => $this->contracts->listPaginatedForCompany(
                $company->id,
                $contractsQuery,
            ),
            'contractsQuery' => $contractsQuery,
            // Stats contextuelles affichées sous le titre de l'onglet —
            // bougent avec le filtre période (chantier N.1.fixes).
            'contractsStats' => $this->contracts->statsForCompany(
                $company->id,
                $contractsQuery->periodStart,
                $contractsQuery->periodEnd,
            ),
            // Plage continue `[firstYear..currentRealYear]` pour les pills
            // de filtre rapide année. Tableau vide si aucun contrat.
            'contractsAvailableYears' => $this->contracts->availableYearsRangeForCompany(
                $company->id,
                $detail->currentRealYear,
            ),
            // Onglet Fiscalité — breakdown par véhicule pour l'année
            // sélectionnée + plage continue d'années pour les pills.
            'companyFiscal' => $this->companies->fiscalBreakdownForYear(
                $company->id,
                $fiscalYear,
            ),
            // Onglet Facturation (Phase 14.D V1.2) — récap mensuel pour
            // l'année sélectionnée + même plage que les contrats pour
            // les pills (cohérence : on ne propose que les années où
            // l'entreprise a au moins un contrat plausible).
            'companyBilling' => $this->companies->billingForYear(
                $company->id,
                $billingYear,
            ),
            'billingYear' => $billingYear,
            // Phase 11 D4 — Déclarations fiscales :
            //   - `pendingDeclarations` : alerte « À finaliser » sur
            //     onglet Vue d'ensemble (contrats existent + pas de
            //     déclaration `generated` active, deadline 30/04/N+1)
            //   - `fiscalActiveDeclaration` : déclaration active pour
            //     l'année sélectionnée de l'onglet Fiscalité (sert le
            //     CTA « Préparer la déclaration » ou le lien vers la
            //     déclaration existante)
            'pendingDeclarations' => $this->pendingDeclarations->pendingForCompany($company->id),
            'fiscalActiveDeclaration' => $this->resolveActiveDeclarationForYear(
                $company->id,
                $fiscalYear,
            ),
        ]);
    }

    private function resolveActiveDeclarationForYear(int $companyId, int $year): ?DeclarationListItemData
    {
        $active = $this->declarations->findActiveForCompanyYear($companyId, $year);
        if ($active === null) {
            return null;
        }

        return DeclarationListItemData::fromModel($active->load('company'));
    }

    public function create(): Response
    {
        return Inertia::render('User/Companies/Create/Index', [
            'colors' => $this->companies->colorOptions(),
        ]);
    }

    public function store(StoreCompanyData $data): RedirectResponse
    {
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
}
