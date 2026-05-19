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
 * Read-only declaration endpoints (index / show / PDF download).
 *
 * The Show endpoint unifies the read view and the interactive review:
 * when the declaration is a Draft/Deferred head, the review-only props
 * (`preview`, `obsoleteReasons`, `riskSettings`) are added.
 */
final class DeclarationController extends Controller
{
    public function __construct(
        private readonly FiscalDeclarationReadRepositoryInterface $reader,
        private readonly DeclarationPreviewService $previewService,
        private readonly DeclarationFiscalEngine $engine,
    ) {}

    /**
     * List declarations with filters and pagination.
     */
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

    /**
     * Render a declaration. Behaviour depends on the lifecycle status:
     * lecture pure for Generated and intermediate drafts, interactive
     * review for the canonical Draft/Deferred head.
     */
    public function show(FiscalDeclaration $declaration): InertiaResponse
    {
        Gate::authorize('view', $declaration);

        $declaration->load('company', 'supersededBy.company');
        $predecessor = $this->reader->findPredecessorOf($declaration->id);
        $successor = $declaration->supersededBy;

        // Canonical head of the (company, year) pair: the one returned by
        // `findCurrentForCompanyYear`. Lets the UI distinguish the head
        // (editable) from intermediate or orphan drafts (deletion only).
        $canonicalHead = $this->reader->findCurrentForCompanyYear(
            $declaration->company_id,
            $declaration->fiscal_year,
        );

        $isEditableDraft = (
            $declaration->status === FiscalDeclarationStatus::Draft
            || $declaration->status === FiscalDeclarationStatus::Deferred
        ) && $canonicalHead !== null && $canonicalHead->id === $declaration->id;

        // Persisted snapshot path is eager; recompute-on-the-fly path is
        // deferred so the mount is not blocked by engine->compute(). The
        // editable-draft branch always recomputes to reflect live cluster
        // decisions, even when a residual payload exists on the draft.
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
            'predecessorDeclaration' => $predecessor !== null
                ? DeclarationListItemData::fromModel($predecessor->load('company'))
                : null,
            'successorDeclaration' => $successor !== null
                ? DeclarationListItemData::fromModel($successor)
                : null,
            'canonicalHeadDeclarationId' => $canonicalHead?->id,
        ];

        // Editable-draft extras. `obsolete_reasons` parsing is delegated to
        // InvalidationReasonData::listFromRaw which centralises validation,
        // try/catch and logging on the `declarations` channel.
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

    /**
     * Stream the generated PDF binary as an attachment.
     */
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
