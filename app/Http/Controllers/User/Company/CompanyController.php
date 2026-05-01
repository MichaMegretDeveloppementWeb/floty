<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Company;

use App\Contracts\Repositories\User\Company\CompanyWriteRepositoryInterface;
use App\Data\User\Company\StoreCompanyData;
use App\Fiscal\Resolver\FiscalYearResolver;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Company\CompanyQueryService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyQueryService $companies,
        private readonly CompanyWriteRepositoryInterface $companyWrite,
        private readonly FiscalYearResolver $fiscalYear,
    ) {}

    public function index(): Response
    {
        return Inertia::render('User/Companies/Index/Index', [
            'companies' => $this->companies->listForFleetView($this->fiscalYear->resolve()),
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
        $this->companyWrite->create($data);

        return redirect()
            ->route('user.companies.index')
            ->with('toast-success', 'Entreprise créée.');
    }
}
