<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\FiscalDeclaration;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Représentation d'une déclaration fiscale annuelle pour un couple
 * `(company, fiscal_year)` (Phase 11 D1, ADR-0015 § 5.1 rev. 1.1).
 *
 * DTO de base utilisé en liste (Index) et en détail (Show) ; sera
 * enrichi en D4 par les `clusters` de revue. La chaîne d'obsolescence
 * est exposée via `isObsolete` + `supersededById` + métadonnées de
 * remplacement.
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
        /** ISO 8601 (Y-m-d). Null si pas encore générée. */
        public ?string $generatedAt,
        public ?string $generatedPdfHash,
        public bool $isObsolete,
        /** ISO 8601 (Y-m-d\TH:i:sP). Null si non obsolète. */
        public ?string $obsoleteAt,
        public ?int $supersededById,
        #[DataCollectionOf(InvalidationReasonData::class)]
        public ?array $obsoleteReasons,
    ) {}

    public static function fromModel(FiscalDeclaration $declaration): self
    {
        $reasons = null;
        if (is_array($declaration->obsolete_reasons)) {
            $reasons = array_values(array_map(
                static fn (array $raw): InvalidationReasonData => InvalidationReasonData::fromArray($raw),
                $declaration->obsolete_reasons,
            ));
        }

        return new self(
            id: $declaration->id,
            companyId: $declaration->company_id,
            companyShortCode: $declaration->company->short_code,
            companyLegalName: $declaration->company->legal_name,
            fiscalYear: $declaration->fiscal_year,
            status: $declaration->status,
            generatedAt: $declaration->generated_at?->toDateString(),
            generatedPdfHash: $declaration->generated_pdf_hash,
            isObsolete: $declaration->is_obsolete,
            obsoleteAt: $declaration->obsolete_at?->toIso8601String(),
            supersededById: $declaration->superseded_by_id,
            obsoleteReasons: $reasons,
        );
    }
}
