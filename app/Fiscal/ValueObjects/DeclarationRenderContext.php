<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Actions\FiscalDeclaration\GenerateDeclarationAction;
use App\Data\User\FiscalDeclaration\DeclarationPreviewData;
use App\Services\Pdf\BladeDomPdfDeclarationRenderer;
use Carbon\CarbonImmutable;

/**
 * Complete render context for a fiscal declaration PDF.
 *
 * Carries the three information sources needed to produce the
 * documentary annex PDF:
 *
 *   - {@see $preview}: re-detected clusters enriched with persisted
 *     decisions (source for LCD chains).
 *   - {@see $snapshot}: fiscal amounts post-Requalified decisions
 *     (source of truth for the PDF).
 *   - {@see $reference}: human-readable number
 *     `DECL-{shortCode}-{year}-{NNNN}` shown in the header and seal.
 *   - {@see $generatedAt}: generation timestamp, shown in the seal and
 *     footer.
 *
 * Built ad-hoc by
 * {@see GenerateDeclarationAction};
 * consumed by {@see BladeDomPdfDeclarationRenderer}.
 */
final readonly class DeclarationRenderContext
{
    public function __construct(
        public DeclarationPreviewData $preview,
        public FiscalDeclarationSnapshot $snapshot,
        public string $reference,
        public CarbonImmutable $generatedAt,
    ) {}
}
