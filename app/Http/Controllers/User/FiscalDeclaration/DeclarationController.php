<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\FiscalDeclaration;

use App\Actions\FiscalDeclaration\CreateDraftDeclarationAction;
use App\Actions\FiscalDeclaration\GenerateDeclarationAction;
use App\Actions\FiscalDeclaration\MarkDeclarationAsDeferredAction;
use App\Actions\FiscalDeclaration\RegenerateDeclarationAction;
use App\Actions\FiscalDeclaration\StoreReviewDecisionAction;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Data\Shared\Listing\PaginationMetaData;
use App\Data\User\FiscalDeclaration\DeclarationIndexQueryData;
use App\Data\User\FiscalDeclaration\DeclarationListItemData;
use App\Data\User\FiscalDeclaration\FiscalDeclarationData;
use App\Data\User\FiscalDeclaration\PaginatedDeclarationListData;
use App\Data\User\FiscalDeclaration\PrepareDeclarationData;
use App\Data\User\FiscalReviewDecision\StoreReviewDecisionData;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Http\Controllers\Controller;
use App\Models\FiscalDeclaration;
use App\Services\Fiscal\RiskDetection\DeclarationPreviewService;
use App\Services\Pdf\DeclarationPdfStorage;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôleur des déclarations fiscales (Phase 11 D4). Slim conforme
 * ADR-0013 : pas de logique métier, délégation aux Actions D3 et au
 * `DeclarationPreviewService`.
 *
 * Endpoints :
 *   - GET    /declarations                       index
 *   - POST   /declarations/prepare               crée un draft pour (company, year)
 *   - GET    /declarations/{declaration}         show
 *   - GET    /declarations/{declaration}/review  page de revue interactive
 *   - POST   /declarations/{declaration}/decisions     persiste une décision cluster
 *   - POST   /declarations/{declaration}/mark-deferred passe draft → deferred
 *   - POST   /declarations/{declaration}/generate      verrouille en generated + PDF
 *   - POST   /declarations/{declaration}/regenerate    crée nouveau draft + chaîne
 *   - GET    /declarations/{declaration}/download      sert le PDF binaire
 */
final class DeclarationController extends Controller
{
    public function __construct(
        private readonly FiscalDeclarationReadRepositoryInterface $reader,
        private readonly DeclarationPreviewService $previewService,
    ) {}

    public function index(DeclarationIndexQueryData $query): InertiaResponse
    {
        Gate::authorize('viewAny', FiscalDeclaration::class);

        $paginator = $this->reader->paginateForIndex($query);

        $items = array_map(
            static fn (FiscalDeclaration $d): DeclarationListItemData => DeclarationListItemData::fromModel($d),
            $paginator->items(),
        );

        $companies = $this->reader->findCompanyOptions()->map(static fn ($c) => [
            'id' => $c->id,
            'shortCode' => $c->short_code,
            'legalName' => $c->legal_name,
        ])->values()->all();

        return Inertia::render('User/Declarations/Index/Index', [
            'declarations' => new PaginatedDeclarationListData(
                data: $items,
                meta: PaginationMetaData::fromPaginator($paginator),
            ),
            'query' => $query,
            'options' => [
                'companies' => $companies,
                'yearBounds' => $this->reader->findYearBounds(),
            ],
            'hasAnyDeclaration' => $this->reader->existsAny(),
        ]);
    }

    public function show(FiscalDeclaration $declaration): InertiaResponse
    {
        Gate::authorize('view', $declaration);

        return Inertia::render('User/Declarations/Show/Index', [
            'declaration' => FiscalDeclarationData::fromModel($declaration->load('company')),
            'history' => $this->reader
                ->findHistoryForCompanyYear($declaration->company_id, $declaration->fiscal_year)
                ->map(static fn (FiscalDeclaration $d): DeclarationListItemData => DeclarationListItemData::fromModel($d->load('company')))
                ->values()
                ->all(),
        ]);
    }

    public function review(FiscalDeclaration $declaration): InertiaResponse|RedirectResponse
    {
        Gate::authorize('view', $declaration);

        // Une déclaration `generated` ou obsolète n'est pas révisable :
        // on redirige vers Show qui présente le snapshot ou le bouton
        // de régénération.
        if (
            $declaration->status === FiscalDeclarationStatus::Generated
            || $declaration->is_obsolete
        ) {
            return redirect()->route('user.declarations.show', ['declaration' => $declaration->id]);
        }

        $preview = $this->previewService->preview($declaration->company_id, $declaration->fiscal_year);

        return Inertia::render('User/Declarations/Review/Index', [
            'declaration' => FiscalDeclarationData::fromModel($declaration->load('company')),
            'preview' => $preview,
        ]);
    }

    public function prepare(
        PrepareDeclarationData $data,
        CreateDraftDeclarationAction $action,
    ): RedirectResponse {
        Gate::authorize('create', FiscalDeclaration::class);

        try {
            $declaration = $action->execute($data->companyId, $data->fiscalYear);
        } catch (DomainException $e) {
            return back()->with('toast-error', $e->getMessage());
        }

        return redirect()
            ->route('user.declarations.review', ['declaration' => $declaration->id])
            ->with('toast-success', sprintf(
                'Déclaration %d préparée. Décidez chaque cluster avant de générer.',
                $data->fiscalYear,
            ));
    }

    public function storeDecision(
        StoreReviewDecisionData $data,
        Request $request,
        FiscalDeclaration $declaration,
        StoreReviewDecisionAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $declaration);

        $user = $request->user();
        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        // Sécurité applicative : la décision doit concerner la même
        // (company, year) que la déclaration ciblée par la route.
        if (
            $data->companyId !== $declaration->company_id
            || $data->fiscalYear !== $declaration->fiscal_year
        ) {
            return back()->with(
                'toast-error',
                'Le périmètre de la décision ne correspond pas à la déclaration.',
            );
        }

        try {
            $action->execute($data, $user->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('toast-error', $e->getMessage());
        }

        return back()->with('toast-success', 'Décision enregistrée.');
    }

    public function markDeferred(
        FiscalDeclaration $declaration,
        MarkDeclarationAsDeferredAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $declaration);

        try {
            $action->execute($declaration->id);
        } catch (DomainException $e) {
            return back()->with('toast-error', $e->getMessage());
        }

        return back()->with('toast-success', 'Déclaration mise de côté.');
    }

    public function generate(
        FiscalDeclaration $declaration,
        GenerateDeclarationAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $declaration);

        try {
            $generated = $action->execute($declaration->id);
        } catch (DomainException $e) {
            return back()->with('toast-error', $e->getMessage());
        }

        return redirect()
            ->route('user.declarations.show', ['declaration' => $generated->id])
            ->with('toast-success', sprintf(
                'Déclaration %s %d générée.',
                $generated->company->short_code,
                $generated->fiscal_year,
            ));
    }

    public function regenerate(
        FiscalDeclaration $declaration,
        RegenerateDeclarationAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $declaration);

        try {
            $newDeclaration = $action->execute($declaration->id);
        } catch (DomainException $e) {
            return back()->with('toast-error', $e->getMessage());
        }

        return redirect()
            ->route('user.declarations.review', ['declaration' => $newDeclaration->id])
            ->with('toast-success', 'Nouvelle déclaration créée. Reprise des décisions par fingerprint.');
    }

    public function download(FiscalDeclaration $declaration, DeclarationPdfStorage $storage): Response
    {
        Gate::authorize('view', $declaration);

        if ($declaration->generated_pdf_path === null) {
            abort(Response::HTTP_NOT_FOUND, 'Aucun PDF associé à cette déclaration.');
        }

        $binary = $storage->read($declaration->generated_pdf_path);
        if ($binary === null) {
            abort(Response::HTTP_NOT_FOUND, 'Fichier PDF introuvable sur le serveur.');
        }

        return new Response(
            $binary,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf(
                    'attachment; filename="declaration-%s-%d.pdf"',
                    $declaration->company->short_code,
                    $declaration->fiscal_year,
                ),
            ],
        );
    }
}
