<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\FiscalDeclaration;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Declarations index row: minimal identity + status + obsolescence flags
 * + generation metadata.
 */
#[TypeScript]
final class DeclarationListItemData extends Data
{
    public function __construct(
        public int $id,
        public int $companyId,
        public string $companyShortCode,
        public string $companyLegalName,
        public int $fiscalYear,
        public FiscalDeclarationStatus $status,
        /** Human-readable `DECL-{shortCode}-{year}-{NNNN}`. Null until generated. */
        public ?string $reference,
        /**
         * Unified internal label used across the frontend (timeline,
         * sub-mentions, banners): `reference` when generated, otherwise
         * `Brouillon #{id}`. Centralises the `?? Brouillon #N` fallback.
         */
        public string $internalLabel,
        public bool $isObsolete,
        /** ISO 8601 (Y-m-d). Null until generated. */
        public ?string $generatedAt,
        public ?string $generatedPdfHash,
        public ?int $supersededById,
        /**
         * True when a Draft chained through `superseded_by_id` exists and
         * points at this declaration. Drives the "Régénération en cours"
         * pill on the index. `null` when not computed by `fromModel()`.
         */
        public ?bool $hasRegenerationInProgress = null,
        /**
         * Predecessor reference (the declaration this one replaces).
         * Populated when the `supersedes` relation is loaded.
         */
        public ?string $predecessorReference = null,
        /** Predecessor id; lets the sub-mention link to the Show page. */
        public ?int $predecessorId = null,
        /**
         * Successor reference (the declaration that replaces this one).
         * Populated when the `supersededBy` relation is loaded.
         */
        public ?string $successorReference = null,
        /**
         * Successor status; lets the UI distinguish "Régénération en
         * cours" (Draft) from "Remplacée par" (Generated).
         */
        public ?FiscalDeclarationStatus $successorStatus = null,
    ) {}

    public static function fromModel(FiscalDeclaration $declaration): self
    {
        $predecessor = self::resolvePredecessor($declaration);
        $successor = self::resolveSuccessor($declaration);

        return new self(
            id: $declaration->id,
            companyId: $declaration->company_id,
            companyShortCode: $declaration->company->short_code,
            companyLegalName: $declaration->company->legal_name,
            fiscalYear: $declaration->fiscal_year,
            status: $declaration->status,
            reference: $declaration->reference,
            internalLabel: $declaration->reference ?? sprintf('Brouillon #%d', $declaration->id),
            isObsolete: $declaration->is_obsolete,
            generatedAt: $declaration->generated_at?->toDateString(),
            generatedPdfHash: $declaration->generated_pdf_hash,
            supersededById: $declaration->superseded_by_id,
            hasRegenerationInProgress: self::computeRegenerationFlag($declaration),
            predecessorReference: $predecessor?->reference,
            predecessorId: $predecessor?->id,
            successorReference: $successor?->reference,
            successorStatus: $successor?->status,
        );
    }

    /**
     * True when the loaded `supersededBy` relation points to a Draft.
     * Null when the relation is not eager-loaded (avoids silent N+1).
     */
    private static function computeRegenerationFlag(FiscalDeclaration $declaration): ?bool
    {
        if (! $declaration->relationLoaded('supersededBy')) {
            return null;
        }

        $successor = $declaration->supersededBy;
        if ($successor === null) {
            return false;
        }

        return $successor->status === FiscalDeclarationStatus::Draft;
    }

    private static function resolvePredecessor(FiscalDeclaration $declaration): ?FiscalDeclaration
    {
        if (! $declaration->relationLoaded('supersedes')) {
            return null;
        }

        return $declaration->supersedes;
    }

    private static function resolveSuccessor(FiscalDeclaration $declaration): ?FiscalDeclaration
    {
        if (! $declaration->relationLoaded('supersededBy')) {
            return null;
        }

        return $declaration->supersededBy;
    }
}
