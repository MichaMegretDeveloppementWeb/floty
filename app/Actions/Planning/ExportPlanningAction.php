<?php

declare(strict_types=1);

namespace App\Actions\Planning;

use App\Contracts\Pdf\PlanningPdfRendererInterface;
use App\Data\User\Planning\PlanningExportRequestData;
use App\DTO\Planning\PlanningExportData;
use App\Services\Planning\PlanningExportService;

/**
 * Builds the planning export PDF on demand · assembles the render context
 * (server-side recompute) then renders the binary. The document is
 * ephemeral · it is streamed back to the browser, never persisted.
 */
final readonly class ExportPlanningAction
{
    public function __construct(
        private PlanningExportService $exportService,
        private PlanningPdfRendererInterface $renderer,
    ) {}

    /**
     * @return array{binary: string, filename: string}
     */
    public function execute(PlanningExportRequestData $request): array
    {
        $data = $this->exportService->build($request);

        return [
            'binary' => $this->renderer->render($data),
            'filename' => $this->filename($data),
        ];
    }

    private function filename(PlanningExportData $data): string
    {
        return $data->companyShortCode !== null
            ? sprintf('floty-planning-%s-%d.pdf', $data->companyShortCode, $data->year)
            : sprintf('floty-planning-%d.pdf', $data->year);
    }
}
