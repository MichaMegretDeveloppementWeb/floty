<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\FiscalDeclaration;

use App\Actions\FiscalDeclaration\CreateDraftDeclarationAction;
use App\Actions\FiscalDeclaration\DiscardDraftDeclarationAction;
use App\Actions\FiscalDeclaration\GenerateDeclarationAction;
use App\Actions\FiscalDeclaration\MarkDeclarationAsDeferredAction;
use App\Actions\FiscalDeclaration\ModifyGeneratedDeclarationAction;
use App\Actions\FiscalDeclaration\RegenerateDeclarationAction;
use App\Actions\FiscalDeclaration\StoreReviewDecisionAction;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Data\Shared\Listing\PaginationMetaData;
use App\Data\User\FiscalDeclaration\DeclarationIndexQueryData;
use App\Data\User\FiscalDeclaration\DeclarationListItemData;
use App\Data\User\FiscalDeclaration\FiscalDeclarationData;
use App\Data\User\FiscalDeclaration\FiscalDeclarationSnapshotData;
use App\Data\User\FiscalDeclaration\InvalidationReasonData;
use App\Data\User\FiscalDeclaration\PaginatedDeclarationListData;
use App\Data\User\FiscalDeclaration\PrepareDeclarationData;
use App\Data\User\FiscalReviewDecision\StoreReviewDecisionData;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Http\Controllers\Controller;
use App\Models\FiscalDeclaration;
use App\Services\Fiscal\Declaration\DeclarationFiscalEngine;
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
 *   - POST   /declarations/{declaration}/modify        S5 → S7 volontaire (D5.10.E)
 *   - DELETE /declarations/{declaration}                supprime un brouillon (D5.10.E)
 *   - GET    /declarations/{declaration}/download      sert le PDF binaire
 */
final class DeclarationController extends Controller
{
    public function __construct(
        private readonly FiscalDeclarationReadRepositoryInterface $reader,
        private readonly DeclarationPreviewService $previewService,
        private readonly DeclarationFiscalEngine $engine,
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

        $declaration->load('company', 'supersededBy.company');
        $predecessor = $this->reader->findPredecessorOf($declaration->id);
        $successor = $declaration->supersededBy;

        // Phase 13 D5.10.H · head canonique du couple (= la déclaration
        // « current » au sens de `findCurrentForCompanyYear`). Permet à
        // l'UI Show de distinguer le head (édition autorisée) des
        // brouillons intermédiaires/orphelins (suppression uniquement).
        $canonicalHead = $this->reader->findCurrentForCompanyYear(
            $declaration->company_id,
            $declaration->fiscal_year,
        );

        return Inertia::render('User/Declarations/Show/Index', [
            'declaration' => FiscalDeclarationData::fromModel($declaration),
            'snapshot' => $this->resolveSnapshotData($declaration),
            'history' => $this->reader
                ->findHistoryForCompanyYear($declaration->company_id, $declaration->fiscal_year)
                ->map(static fn (FiscalDeclaration $d): DeclarationListItemData => DeclarationListItemData::fromModel($d->load('company')))
                ->values()
                ->all(),
            // Phase 11 D5.8.3 · si cette déclaration remplace une version
            // obsolète (chaîne `superseded_by_id`), expose-la pour que la
            // page rende un mini banner narratif rappelant la traçabilité.
            'predecessorDeclaration' => $predecessor !== null
                ? DeclarationListItemData::fromModel($predecessor->load('company'))
                : null,
            // Phase 12 D5.9.D · si cette déclaration est elle-même
            // remplacée (un Draft chaîné en cours de régénération
            // pointe vers une autre déclaration), expose-le pour que
            // `<PdfCard>` propose « Reprendre la régénération en cours »
            // au lieu d'un nouveau « Régénérer » qui créerait un
            // brouillon orphelin.
            'successorDeclaration' => $successor !== null
                ? DeclarationListItemData::fromModel($successor)
                : null,
            'canonicalHeadDeclarationId' => $canonicalHead?->id,
        ]);
    }

    /**
     * Résout le snapshot fiscal d'une déclaration : priorité au
     * payload persisté en BDD si présent (Phase 11 D5.7.5 audit B5),
     * fallback recalcul via {@see DeclarationFiscalEngine} sinon.
     *
     * **Pourquoi la priorité au persisté** : le payload BDD a été figé
     * au moment de `markAsGenerated()` et reflète **exactement** les
     * montants du PDF historique. Le recalcul à la volée peut
     * diverger si une mutation post-génération n'a pas déclenché
     * l'invalidation. Pour une déclaration `Generated` non obsolète,
     * les deux sources doivent normalement coïncider ; le persisté
     * fait foi en cas d'écart.
     *
     * **Quand recalculer (fallback)** :
     *   - Déclaration `Draft` ou `Deferred` : pas encore générée, donc
     *     payload null, preview en direct.
     *   - Déclaration historique générée pré-D5.7.5 : payload null
     *     (pas de backfill auto), recalcul rétrocompat.
     */
    private function resolveSnapshotData(FiscalDeclaration $declaration): FiscalDeclarationSnapshotData
    {
        if (is_array($declaration->generated_snapshot_payload)) {
            return FiscalDeclarationSnapshotData::from($declaration->generated_snapshot_payload);
        }

        $snapshot = $this->engine->compute($declaration->company_id, $declaration->fiscal_year);

        return FiscalDeclarationSnapshotData::fromValueObject($snapshot);
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

        // Phase 13 D5.10.H · brouillon intermédiaire orphelin (un autre
        // brouillon plus récent existe et est le head canonique) ·
        // l'édition n'a aucun sens, l'utilisateur ne peut que le
        // supprimer. On redirige vers Show pour exposer le message
        // explicite + le bouton Supprimer du header.
        $canonicalHead = $this->reader->findCurrentForCompanyYear(
            $declaration->company_id,
            $declaration->fiscal_year,
        );
        if ($canonicalHead !== null && $canonicalHead->id !== $declaration->id) {
            return redirect()->route('user.declarations.show', ['declaration' => $declaration->id]);
        }

        $preview = $this->previewService->preview($declaration->company_id, $declaration->fiscal_year);

        // En page Review, la déclaration est par construction `draft`
        // ou `deferred` (cf. redirect ci-dessus pour Generated/Obsolète),
        // donc pas de payload persisté. On recalcule toujours, ce qui
        // est cohérent avec le rôle « prévisualisation interactive »
        // de la page : les décisions Conserver/Requalifier en cours
        // doivent se refléter en direct sur la `FiscalSummaryCard`.
        $snapshot = $this->engine->compute($declaration->company_id, $declaration->fiscal_year);

        // Phase 11 D5.8.3 · si ce Draft est chaîné (régénération en
        // cours), expose la version obsolète remplacée pour permettre
        // au `<ReviewContextBanner>` de basculer en mode régénération
        // avec le contexte des motifs d'obsolescence.
        $predecessor = $this->reader->findPredecessorOf($declaration->id);
        $obsoleteReasons = [];
        if ($predecessor !== null && $predecessor->obsolete_reasons !== null) {
            $obsoleteReasons = array_map(
                static fn (array $raw): InvalidationReasonData => InvalidationReasonData::fromArray($raw),
                $predecessor->obsolete_reasons,
            );
        }

        $canonicalHead = $this->reader->findCurrentForCompanyYear(
            $declaration->company_id,
            $declaration->fiscal_year,
        );

        return Inertia::render('User/Declarations/Review/Index', [
            'declaration' => FiscalDeclarationData::fromModel($declaration->load('company')),
            'preview' => $preview,
            'snapshot' => FiscalDeclarationSnapshotData::fromValueObject($snapshot),
            'predecessorDeclaration' => $predecessor !== null
                ? DeclarationListItemData::fromModel($predecessor->load('company'))
                : null,
            'obsoleteReasons' => $obsoleteReasons,
            'canonicalHeadDeclarationId' => $canonicalHead?->id,
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

    /**
     * Phase 13 D5.10.E · transition volontaire S5 → S7. Permet à
     * l'utilisateur de modifier une déclaration générée et active sans
     * attendre une mutation involontaire de périmètre. La déclaration
     * courante devient obsolète avec le motif `VoluntaryModification`,
     * un nouveau brouillon est créé et chaîné. Si l'utilisateur change
     * d'avis, `destroy` saura ré-activer la déclaration précédente.
     */
    public function modify(
        Request $request,
        FiscalDeclaration $declaration,
        ModifyGeneratedDeclarationAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $declaration);

        $user = $request->user();
        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        try {
            $newDraft = $action->execute(
                $declaration->id,
                $user->id,
                $user->full_name,
            );
        } catch (DomainException $e) {
            return back()->with('toast-error', $e->getMessage());
        }

        return redirect()
            ->route('user.declarations.review', ['declaration' => $newDraft->id])
            ->with('toast-success', sprintf(
                'Nouveau brouillon de modification créé. La déclaration %s est désormais obsolète mais reste consultable.',
                $declaration->reference ?? sprintf('#%d', $declaration->id),
            ));
    }

    /**
     * Phase 13 D5.10.E · suppression d'un brouillon. Soft delete +
     * gestion intelligente du predecessor : ré-activation si l'obsolescence
     * était purement volontaire, simple unlink sinon.
     */
    public function destroy(
        FiscalDeclaration $declaration,
        DiscardDraftDeclarationAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $declaration);

        try {
            $action->execute($declaration->id);
        } catch (DomainException $e) {
            return back()->with('toast-error', $e->getMessage());
        }

        return redirect()
            ->route('user.declarations.index')
            ->with('toast-success', 'Brouillon supprimé.');
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
