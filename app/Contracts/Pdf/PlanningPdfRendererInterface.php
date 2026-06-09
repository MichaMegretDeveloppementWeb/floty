<?php

declare(strict_types=1);

namespace App\Contracts\Pdf;

use App\DTO\Planning\PlanningExportData;

/**
 * Renders the planning export PDF (on-demand report, not persisted).
 *
 * Implementation · {@see App\Services\Pdf\BladeDomPdfPlanningRenderer}
 * (Blade + DomPDF, default binding). The orientation and the Blade view
 * are chosen from {@see PlanningExportData::$mode} (complete = landscape
 * weekly grid, vehicle = portrait per-vehicle sheet).
 */
interface PlanningPdfRendererInterface
{
    /**
     * Renders the export PDF binary from its complete render context.
     */
    public function render(PlanningExportData $data): string;
}
