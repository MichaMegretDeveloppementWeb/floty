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
 * Représentation d'une déclaration fiscale annuelle pour un couple
 * `(company, fiscal_year)` (Phase 11 D1, ADR-0015 § 5.1 rev. 1.1).
 *
 * DTO de base utilisé en liste (Index) et en détail (Show) ; sera
 * enrichi en D4 par les `clusters` de revue. La chaîne d'obsolescence
 * est exposée via `isObsolete` + `supersededById` + métadonnées de
 * remplacement.
 *
 * **Coexistence des 2 hashes** (Lot 5 D8 · F-19D2-007) ·
 *
 *   - `generatedPdfHash` (figé en BDD au moment de la génération PDF) ·
 *     empreinte immuable du payload tel qu'il existait à la génération.
 *     Sert d'ancre pour l'audit régulatoire (« cette déclaration émise
 *     contenait exactement ce payload »). Persisté dans
 *     `fiscal_declarations.generated_pdf_hash`. Null tant que pas générée.
 *
 *   - `snapshotHash` (recalculé live à chaque hydratation via
 *     {@see SnapshotHashCalculator::compute()}) · empreinte de l'état
 *     courant du `generated_snapshot_payload` JSON tel qu'il vit en
 *     BDD aujourd'hui. Sert à détecter une éventuelle altération du
 *     snapshot persisté (intégrité documentaire). Mémoïsé intra-requête
 *     par cache statique du calculator · pas de re-calcul à chaque
 *     accès dans la même requête HTTP.
 *
 *   Si `generatedPdfHash !== snapshotHash` · le snapshot persisté a
 *   divergé du PDF émis (situation anormale · à investiguer).
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
        /** Numéro lisible `DECL-{shortCode}-{year}-{NNNN}`. Null si pas encore générée (Phase 11 D5.3). */
        public ?string $reference,
        /**
         * Phase 13 D5.10.P · libellé d'affichage interne · « DECL-XXX »
         * si générée, sinon « Brouillon #N ». Centralise la logique
         * `?? Brouillon #N` pour éviter sa duplication côté frontend.
         * Aligné sur `DeclarationListItemData::internalLabel`.
         */
        public string $internalLabel,
        /** ISO 8601 (Y-m-d). Null si pas encore générée. */
        public ?string $generatedAt,
        public ?string $generatedPdfHash,
        public bool $isObsolete,
        /** ISO 8601 (Y-m-d\TH:i:sP). Null si non obsolète. */
        public ?string $obsoleteAt,
        public ?int $supersededById,
        #[DataCollectionOf(InvalidationReasonData::class)]
        public ?array $obsoleteReasons,
        /**
         * Phase 13 D5.10.J · SHA-256 du snapshot canonique = empreinte
         * fiscale du document. Affichée à l'identique sur le PDF (bloc
         * sceau) et sur la page Show de la déclaration · permet à toute
         * partie qui dispose du snapshot de vérifier l'intégrité du
         * document fiscal. Null pour les déclarations non encore
         * générées (pas de snapshot persisté).
         */
        public ?string $snapshotHash = null,
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
        );
    }
}
