<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\FiscalDeclaration;
use App\Services\Fiscal\SnapshotHashCalculator;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Annual fiscal declaration for a `(company, fiscal_year)` couple
 * (ADR-0015 § 5.1 rev. 1.1).
 *
 * Used both in list (Index) and detail (Show); enriched with review
 * `clusters` further on. The obsolescence chain is exposed via
 * `isObsolete` + `supersededById` + replacement metadata.
 *
 * Two coexisting hashes:
 *   - `generatedPdfHash`: frozen at PDF generation, immutable fingerprint
 *     of the payload as it was produced. Audit anchor. Null until generated.
 *   - `snapshotHash`: live SHA-256 of the persisted snapshot, computed
 *     through {@see SnapshotHashCalculator::compute()} on each hydration
 *     (memoized within the request). Detects tampering of the stored
 *     snapshot.
 *
 * When `generatedPdfHash !== snapshotHash` the persisted snapshot has
 * diverged from the emitted PDF (anomaly to investigate).
 */
#[TypeScript]
final class FiscalDeclarationData extends Data
{
    /**
     * @param  list<InvalidationReasonData>|null  $obsoleteReasons
     */
    public function __construct(
        public int $id,
        public int $companyId,
        public string $companyShortCode,
        public string $companyLegalName,
        public int $fiscalYear,
        public FiscalDeclarationStatus $status,
        /** `DECL-{shortCode}-{year}-{NNNN}`. Null until generated. */
        public ?string $reference,
        /**
         * Internal display label: `DECL-XXX` when generated, otherwise
         * `Brouillon #N`. Centralises the fallback for the frontend and
         * mirrors `DeclarationListItemData::internalLabel`.
         */
        public string $internalLabel,
        /** ISO 8601 (Y-m-d). Null until generated. */
        public ?string $generatedAt,
        public ?string $generatedPdfHash,
        public bool $isObsolete,
        /** ISO 8601 (Y-m-d\TH:i:sP). Null when not obsolete. */
        public ?string $obsoleteAt,
        public ?int $supersededById,
        #[DataCollectionOf(InvalidationReasonData::class)]
        public ?array $obsoleteReasons,
        /**
         * SHA-256 of the canonical snapshot = fiscal fingerprint of the
         * document. Printed on the PDF seal block and on the Show page so
         * any party with the snapshot can verify integrity. Null when no
         * snapshot is persisted yet.
         */
        public ?string $snapshotHash = null,
        /**
         * Pause reason captured by the user when deferring a draft (max
         * 500 char). Displayed while the draft is deferred, cleared on
         * revert (deferred -> draft) or on generation. Null otherwise.
         */
        public ?string $deferReason = null,
    ) {}

    public static function fromModel(FiscalDeclaration $declaration): self
    {
        // Resilience: a malformed `obsolete_reasons` payload (corrupt
        // JSON, items with the wrong shape) must not break pages that
        // serialise this DTO. `InvalidationReasonData::listFromRaw`
        // centralises the array/try-catch guards and the warning log
        // on the `declarations` channel.
        $reasonsList = InvalidationReasonData::listFromRaw($declaration->obsolete_reasons, $declaration->id);
        $reasons = $reasonsList !== [] ? $reasonsList : null;

        $snapshotHash = is_array($declaration->generated_snapshot_payload)
            ? SnapshotHashCalculator::compute($declaration->generated_snapshot_payload)
            : null;

        return new self(
            id: $declaration->id,
            companyId: $declaration->company_id,
            companyShortCode: $declaration->company->short_code,
            companyLegalName: $declaration->company->legal_name,
            fiscalYear: $declaration->fiscal_year,
            status: $declaration->status,
            reference: $declaration->reference,
            internalLabel: $declaration->reference ?? sprintf('Brouillon #%d', $declaration->id),
            generatedAt: $declaration->generated_at?->toDateString(),
            generatedPdfHash: $declaration->generated_pdf_hash,
            isObsolete: $declaration->is_obsolete,
            obsoleteAt: $declaration->obsolete_at?->toIso8601String(),
            supersededById: $declaration->superseded_by_id,
            obsoleteReasons: $reasons,
            snapshotHash: $snapshotHash,
            deferReason: $declaration->defer_reason,
        );
    }
}
