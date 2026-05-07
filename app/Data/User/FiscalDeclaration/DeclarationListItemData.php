<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\FiscalDeclaration;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Ligne de l'Index Déclarations (Phase 11 D4). Identité minimale +
 * statut + flags d'obsolescence + métadonnées de génération si
 * applicable.
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
        public bool $isObsolete,
        /** ISO 8601 (Y-m-d). Null si pas encore générée. */
        public ?string $generatedAt,
        public ?string $generatedPdfHash,
        public ?int $supersededById,
    ) {}

    public static function fromModel(FiscalDeclaration $declaration): self
    {
        return new self(
            id: $declaration->id,
            companyId: $declaration->company_id,
            companyShortCode: $declaration->company->short_code,
            companyLegalName: $declaration->company->legal_name,
            fiscalYear: $declaration->fiscal_year,
            status: $declaration->status,
            isObsolete: $declaration->is_obsolete,
            generatedAt: $declaration->generated_at?->toDateString(),
            generatedPdfHash: $declaration->generated_pdf_hash,
            supersededById: $declaration->superseded_by_id,
        );
    }
}
