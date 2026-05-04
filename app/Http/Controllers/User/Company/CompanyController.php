<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Company;

use App\Actions\Company\CreateCompanyAction;
use App\Data\User\Company\CompanyIndexQueryData;
use App\Data\User\Company\StoreCompanyData;
use App\Exceptions\Company\CompanyShortCodeCollisionException;
use App\Fiscal\Resolver\FiscalYearResolver;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Company\CompanyQueryService;
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
        private readonly CreateCompanyAction $createCompany,
        private readonly FiscalYearResolver $fiscalYear,
    ) {}

    public function index(CompanyIndexQueryData $query): Response
    {
        return Inertia::render('User/Companies/Index/Index', [
            'companies' => $this->companies->listPaginated($query, $this->fiscalYear->resolve()),
            'query' => $query,
        ]);
    }

    public function show(Company $company, Request $request): Response
    {
        // ADR-0020 D3 : sélecteur d'année **local** à la fiche, lu depuis
        // le query param. Fallback à null → le service applique l'année
        // calendaire réelle. Pas de dépendance au sélecteur global
        // (résolveur fiscal de session non utilisé ici).
        $selectedYear = $this->parseYearQuery($request->query('year'));

        $detail = $this->companies->detail($company->id, $selectedYear);

        if ($detail === null) {
            throw new NotFoundHttpException('Entreprise introuvable.');
        }

        return Inertia::render('User/Companies/Show/Index', [
            'company' => $detail,
        ]);
    }

    /**
     * Parse silencieux du `?year=YYYY` query param. Retourne `null` si
     * absent / invalide / hors plage raisonnable — le service appliquera
     * son fallback (année calendaire réelle).
     */
    private function parseYearQuery(mixed $value): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (! ctype_digit($value)) {
            return null;
        }

        $year = (int) $value;
        // Garde-fou : on accepte 1900..2100 pour ne pas crasher l'UI sur
        // un input délirant (seuils larges, le service tolère les années
        // sans données et retourne des stats à zéro).
        if ($year < 1900 || $year > 2100) {
            return null;
        }

        return $year;
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
