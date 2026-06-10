<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\Pdf\PlanningPdfRendererInterface;
use App\DTO\Planning\PlanningExportData;
use App\DTO\Planning\PlanningExportRowData;
use App\Enums\Planning\PlanningExportMode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * HTML → PDF renderer for the planning export.
 *
 * DejaVu Sans (UTF-8 native in DomPDF), `display: table` / real `<table>`
 * layouts (DomPDF supports neither flexbox nor grid). Orientation and
 * view depend on the mode · « Données complètes » is a wide landscape
 * weekly grid, « Données véhicule » a portrait per-vehicle sheet. The
 * companion {@see renderHtml()} exposes the intermediate HTML so tests
 * assert on content without parsing the binary. Mirrors the formatting
 * conventions of {@see BladeDomPdfDeclarationRenderer}.
 */
final readonly class BladeDomPdfPlanningRenderer implements PlanningPdfRendererInterface
{
    public function render(PlanningExportData $data): string
    {
        [$view, $orientation] = $this->viewAndOrientation($data->mode);

        return Pdf::loadView($view, $this->prepareViewData($data))
            ->setPaper('A4', $orientation)
            ->output();
    }

    /**
     * Renders only the intermediate HTML (no DomPDF pass) · useful for
     * content tests and visual debugging.
     */
    public function renderHtml(PlanningExportData $data): string
    {
        [$view] = $this->viewAndOrientation($data->mode);

        return view($view, $this->prepareViewData($data))->render();
    }

    /**
     * @return array{0: string, 1: 'landscape'|'portrait'}
     */
    private function viewAndOrientation(PlanningExportMode $mode): array
    {
        return $mode === PlanningExportMode::Complete
            ? ['pdf.planning-export-complete', 'landscape']
            : ['pdf.planning-export-vehicles', 'portrait'];
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareViewData(PlanningExportData $data): array
    {
        [$title, $subtitle] = $this->titleAndSubtitle($data->mode);

        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'year' => $data->year,
            'companyName' => $data->companyName,
            'vehicleCount' => count($data->rows),
            'generatedAtLabel' => $data->generatedAt->format('d/m/Y').' à '.$data->generatedAt->format('H:i'),
            'rows' => array_map(fn (PlanningExportRowData $row): array => $this->mapRow($row), $data->rows),
        ];
    }

    /**
     * Document title + subtitle by export mode.
     *
     * @return array{0: string, 1: string}
     */
    private function titleAndSubtitle(PlanningExportMode $mode): array
    {
        return $mode === PlanningExportMode::Complete
            ? ['Planning d\'utilisation de la flotte', 'Répartition hebdomadaire des jours d\'utilisation']
            : ['Inventaire du parc de véhicules', 'Caractéristiques fiscales et montants annuels'];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(PlanningExportRowData $row): array
    {
        $vehicleLabel = trim($row->brand.' '.$row->model);

        return [
            'licensePlate' => $row->licensePlate,
            'vehicleLabel' => $vehicleLabel,
            // Bounded variant for the dense weekly grid · the identity
            // column is nowrap, so an over-long name would steal width from
            // the week columns. The full label stays in the vehicle sheet.
            'vehicleLabelShort' => Str::limit($vehicleLabel, 24),
            'userType' => $row->userType->label(),
            'userTypeShort' => $row->userType->value,
            'energy' => $row->energy->label(),
            'co2Method' => $row->co2Method->label(),
            'co2Value' => $row->co2Value,
            'taxableHorsepower' => $row->taxableHorsepower,
            'pollutantCategory' => $row->pollutantCategory->label(),
            'firstRegistration' => $this->formatDateFr($row->firstFrenchRegistrationDate),
            'weeks' => $row->weeks,
            'weeksOutOfFleet' => $row->weeksOutOfFleet,
            'daysTotal' => $row->daysTotal,
            'fullYearTax' => $this->formatEuros($row->fullYearTax, 0),
            'annualTaxDue' => $this->formatEuros($row->annualTaxDue, 0),
            'dailyRate' => $row->dailyRateCents !== null ? $this->formatEuros($row->dailyRateCents / 100) : null,
            'weeklyRate' => $row->weeklyRateCents !== null ? $this->formatEuros($row->weeklyRateCents / 100) : null,
            'monthlyRate' => $row->monthlyRateCents !== null ? $this->formatEuros($row->monthlyRateCents / 100) : null,
            'exemptions' => $row->exemptions,
            'exitDate' => $row->exitDate !== null ? $this->formatDateFr($row->exitDate) : null,
            'exitReason' => $row->exitReason?->label(),
        ];
    }

    private function formatDateFr(string $isoDate): string
    {
        $parts = explode('-', $isoDate);
        if (count($parts) !== 3) {
            return $isoDate;
        }

        return sprintf('%s/%s/%s', $parts[2], $parts[1], $parts[0]);
    }

    /**
     * French euro formatting · comma decimals, U+202F narrow no-break
     * space as thousands separator and before the symbol. Mirrors
     * {@see BladeDomPdfDeclarationRenderer::formatEuros()}.
     */
    private function formatEuros(float $amount, int $fractionDigits = 2): string
    {
        return number_format($amount, $fractionDigits, ',', "\u{202F}")."\u{202F}€";
    }
}
