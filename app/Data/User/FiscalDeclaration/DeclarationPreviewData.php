<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Sortie agrégée du `DeclarationPreviewService` (Phase 11 D3) :
 * calcul brut + clusters de risque détectés (avec décisions
 * pré-appliquées par fingerprint si trouvées en BDD) + déclaration
 * active courante (si elle existe).
 *
 * Sert d'entrée au rendu PDF (D5) et à la page de revue (D4).
 */
#[TypeScript]
final class DeclarationPreviewData extends Data
{
    /**
     * @param  list<ReviewClusterData>  $clusters
     */
    public function __construct(
        public int $companyId,
        public string $companyShortCode,
        public string $companyLegalName,
        public int $fiscalYear,
        #[DataCollectionOf(ReviewClusterData::class)]
        public array $clusters,
        public int $pendingClustersCount,
        public bool $canGenerate,
        public ?FiscalDeclarationData $declaration,
    ) {}
}
