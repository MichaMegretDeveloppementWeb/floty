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
 *   - GET /declarations/{declaration}             show (lecture pour Generated,
 *                                                 revue interactive pour
 *                                                 Draft/Deferred head canonique,
 *                                                 message intermédiaire pour
 *                                                 brouillon orphelin)
 *   - GET /declarations/{declaration}/download    sert le PDF binaire
 *
 * Lot 5 D12 · fusion Show + Review en un seul écran adaptatif. La route
 * `/review` historique est supprimée · les redirects venant du
 * {@see DeclarationGenerationController} pointent désormais vers `show`.
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

        // Lot 5 D12 · fusion Show + Review · mode B = brouillon head
        // canonique. C'est le seul cas où la revue interactive
        // s'affiche · on sert alors `preview` (RiskDetection),
        // `obsoleteReasons` (chaîne régénération) et `riskSettings`
        // en plus du payload Show standard. Pour Generated, brouillon
        // orphelin ou ancien snapshot persisté, ces props ne sont
        // pas servies (UI lecture pure).
        $isEditableDraft = (
            $declaration->status === FiscalDeclarationStatus::Draft
            || $declaration->status === FiscalDeclarationStatus::Deferred
        ) && $canonicalHead !== null && $canonicalHead->id === $declaration->id;

        // P0.5 (audit perf 2026-05-16 / 08-misc.md P0 #2) · snapshot
        // conditionnel · payload persiste → rendu eager (lecture array
        // quasi-instantanee). Sinon (Draft sans payload) → Inertia::defer
        // pour ne pas bloquer le mount sur engine->compute() complet
        // (~100-500 ms cold). Mode B (Draft head éditable) · on
        // recalcule systématiquement même si un payload résiduel existe
        // côté brouillon · la revue interactive doit refléter le
        // périmètre live (décisions cluster en cours).
        $hasPersistedSnapshot = is_array($declaration->generated_snapshot_payload)
            && ! $isEditableDraft;

        $payload = [
            'declaration' => FiscalDeclarationData::fromModel($declaration),
            'snapshot' => $hasPersistedSnapshot
                ? FiscalDeclarationSnapshotData::from($declaration->generated_snapshot_payload)
                : Inertia::defer(fn () => FiscalDeclarationSnapshotData::fromValueObject(
                    $this->engine->compute($declaration->company_id, $declaration->fiscal_year),
                )),
            'history' => $this->reader
                ->findHistoryForCompanyYear($declaration->company_id, $declaration->fiscal_year)
                ->map(static fn (FiscalDeclaration $d): DeclarationListItemData => DeclarationListItemData::fromModel($d))
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
        ];

        // Lot 5 D12 · props spécifiques mode B (revue interactive).
        // P0.4 · `preview` (RiskDetection ~150-400 ms) en defer ·
        // skeleton fallback côté front via
        // `<Deferred :data="['preview', 'snapshot']">`. Garde-fou
        // résilient sur `obsolete_reasons` éventuellement mal formé en
        // BDD · délégué à `InvalidationReasonData::listFromRaw` qui
        // centralise checks + try/catch + log canal `declarations`.
        if ($isEditableDraft) {
            $payload['preview'] = Inertia::defer(
                fn () => $this->previewService->preview($declaration->company_id, $declaration->fiscal_year),
            );
            $payload['obsoleteReasons'] = $predecessor !== null
                ? InvalidationReasonData::listFromRaw($predecessor->obsolete_reasons, $predecessor->id)
                : [];
            $payload['riskSettings'] = FiscalRiskSettingsData::fromModel(FiscalRiskSettings::singleton());
        }

        return Inertia::render('User/Declarations/Show/Index', $payload);
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
