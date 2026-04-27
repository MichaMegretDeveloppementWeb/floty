<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Company;

use App\Data\User\Company\StoreCompanyData;
use App\Http\Controllers\Controller;
use App\Services\Company\CompanyQueryService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyController extends Controller
{
    public function __construct(private readonly CompanyQueryService $companies) {}

    public function index(): Response
    {
        return Inertia::render('User/Companies/Index', [
            'companies' => $this->companies->listForFleetView(
                (int) config('floty.fiscal.current_year'),
            ),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('User/Companies/Create', [
            'colors' => $this->companies->colorOptions(),
        ]);
    }

    public function store(StoreCompanyData $data): RedirectResponse
    {
        $this->companies->create($data);

        return redirect()
            ->route('user.companies.index')
            ->with('toast-success', 'Entreprise créée.');
    }
}
