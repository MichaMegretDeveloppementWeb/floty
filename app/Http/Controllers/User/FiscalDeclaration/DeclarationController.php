<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Data\Shared\Listing\PaginationMetaData;
use App\Data\User\FiscalDeclaration\DeclarationIndexQueryData;
use App\Data\User\FiscalDeclaration\DeclarationListItemData;
use App\Data\User\FiscalDeclaration\FiscalDeclarationData;
use App\Data\User\FiscalDeclaration\FiscalDeclarationSnapshotData;
use App\Data\User\FiscalDeclaration\InvalidationReasonData;
use App\Data\User\FiscalDeclaration\PaginatedDeclarationListData;
use App\Data\User\FiscalRiskSettings\FiscalRiskSettingsData;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Http\Controllers\Controller;
use App\Models\FiscalDeclaration;
use App\Models\FiscalRiskSettings;
use App\Services\Fiscal\Declaration\DeclarationFiscalEngine;
use App\Services\Fiscal\RiskDetection\DeclarationPreviewService;
use App\Services\Pdf\DeclarationPdfStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consultation des déclarations fiscales (Phase 11 D4 · slim conforme
 * R7/R10 ADR-0013 après extraction du cycle de génération vers
 * {@see DeclarationGenerationController} et des transitions terminales
 * vers {@see DeclarationLifecycleController} en Lot 4 D13 (F-34-105)).
 *
 * Endpoints read-only ·
 *   - GET /declarations                           index
 *   - GET /declarations/{declaration}             show
 *   - GET /declarations/{declaration}/review      page de revue interactive
 *   - GET /declarations/{declaration}/download    sert le PDF binaire
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
        // Garde-fou résilient · payload `obsolete_reasons` éventuellement
        // mal formé en BDD (cast Eloquent retourne un scalaire, items
        // sans le schéma attendu, etc.) ne doit pas casser la page Review.
        // Délégué à `InvalidationReasonData::listFromRaw` qui centralise
        // les checks `is_array` + try/catch + log canal `declarations`.
        $obsoleteReasons = $predecessor !== null
            ? InvalidationReasonData::listFromRaw($predecessor->obsolete_reasons, $predecessor->id)
            : [];

        return Inertia::render('User/Declarations/Review/Index', [
            'declaration' => FiscalDeclarationData::fromModel($declaration->load('company')),
            'preview' => $preview,
            'snapshot' => FiscalDeclarationSnapshotData::fromValueObject($snapshot),
            'predecessorDeclaration' => $predecessor !== null
                ? DeclarationListItemData::fromModel($predecessor->load('company'))
                : null,
            'obsoleteReasons' => $obsoleteReasons,
            'canonicalHeadDeclarationId' => $canonicalHead?->id,
            // Lot 5 D1 · expose les seuils paramétrables au modal de
            // décision · le texte pédagogique du modal interpole
            // dynamiquement les valeurs (`thresholdLow`, `thresholdHigh`,
            // `countHigh`) au lieu de les hardcoder côté UI.
            'riskSettings' => FiscalRiskSettingsData::fromModel(FiscalRiskSettings::singleton()),
        ]);
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
