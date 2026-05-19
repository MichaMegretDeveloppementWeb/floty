<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Aggregated output of `DeclarationPreviewService`: raw computation +
 * detected risk clusters (with already-applied decisions when found by
 * fingerprint) + current active declaration when one exists.
 *
 * Feeds both PDF rendering and the review page.
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
