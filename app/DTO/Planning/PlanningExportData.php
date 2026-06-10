<?php

declare(strict_types=1);

namespace App\DTO\Planning;

use App\Enums\Planning\PlanningExportMode;
use Carbon\CarbonImmutable;

/**
 * Complete render context for a planning PDF export.
 *
 * Built by {@see App\Services\Planning\PlanningExportService}; consumed
 * by {@see App\Services\Pdf\BladeDomPdfPlanningRenderer}. `companyName` is
 * the company legal name when the export comes from the per-company view
 * (the figures are scoped to it), and `null` from the overview (the header
 * then shows only the vehicle count, not a misleading scope label).
 * `companyShortCode` drives the download filename only.
 */
final readonly class PlanningExportData
{
    /**
     * @param  list<PlanningExportRowData>  $rows
     */
    public function __construct(
        public ?string $companyName,
        public ?string $companyShortCode,
        public int $year,
        public PlanningExportMode $mode,
        public CarbonImmutable $generatedAt,
        public array $rows,
    ) {}
}
