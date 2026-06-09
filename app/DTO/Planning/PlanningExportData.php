<?php

declare(strict_types=1);

namespace App\DTO\Planning;

use App\Enums\Planning\PlanningExportMode;
use Carbon\CarbonImmutable;

/**
 * Complete render context for a planning PDF export.
 *
 * Built by {@see App\Services\Planning\PlanningExportService}; consumed
 * by {@see App\Services\Pdf\BladeDomPdfPlanningRenderer}. `scopeLabel` is
 * the human header (« Flotte entière » or the company legal name);
 * `companyShortCode` drives the download filename only.
 */
final readonly class PlanningExportData
{
    /**
     * @param  list<PlanningExportRowData>  $rows
     */
    public function __construct(
        public string $scopeLabel,
        public ?string $companyShortCode,
        public int $year,
        public PlanningExportMode $mode,
        public CarbonImmutable $generatedAt,
        public array $rows,
    ) {}
}
