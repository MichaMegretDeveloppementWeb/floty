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
use App\Services\Driver\DriverQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyQueryService $companies,
        private readonly DriverQueryService $drivers,
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

    public function show(Company $company): Response
    {
        $detail = $this->companies->detail($company->id);

        if ($detail === null) {
            throw new NotFoundHttpException('Entreprise introuvable.');
        }

        return Inertia::render('User/Companies/Show/Index', [
            'company' => $detail,
            'options' => [
                // Liste plate des drivers pour peupler le picker du modal
                // d'ajout de membership (`AddCompanyDriverModal`). La modale
                // filtre côté front les drivers déjà rattachés à la company.
                'drivers' => $this->drivers->listForOptions(),
            ],
        ]);
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
