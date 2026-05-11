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
        /** Numéro lisible `DECL-{shortCode}-{year}-{NNNN}` (Phase 11 D5.3). Null si pas encore générée. */
        public ?string $reference,
        public bool $isObsolete,
        /** ISO 8601 (Y-m-d). Null si pas encore générée. */
        public ?string $generatedAt,
        public ?string $generatedPdfHash,
        public ?int $supersededById,
        /**
         * Vrai ssi un Draft chaîné existe et pointe vers cette déclaration
         * via `superseded_by_id`. Sert au pill « Régénération en cours »
         * de l'Index Déclarations (amélioration F, populé en D5.8.5).
         * `null` par défaut : non calculé par `fromModel()`.
         */
        public ?bool $hasRegenerationInProgress = null,
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
            reference: $declaration->reference,
            isObsolete: $declaration->is_obsolete,
            generatedAt: $declaration->generated_at?->toDateString(),
            generatedPdfHash: $declaration->generated_pdf_hash,
            supersededById: $declaration->superseded_by_id,
            hasRegenerationInProgress: self::computeRegenerationFlag($declaration),
        );
    }

    /**
     * `true` ssi la relation `supersededBy` est chargée et pointe vers
     * un Draft (Phase 11 D5.8.5). `null` quand la relation n'est pas
     * eager-loadée (économise une requête SQL N+1 quand l'info n'est
     * pas pertinente, par exemple sur la page Show).
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
}
