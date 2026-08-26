<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Data\User\Company\CompanyYearPickerData;
use App\Services\Fiscal\AvailableYearsResolver;

/**
 * Builds the payload of the company + exercise picker modal, shared by
 * the invoices index, the declarations index and the dashboard panels.
 *
 * Both reads are gated behind `Inertia::optional()` on every consumer,
 * so they only run on the partial reload triggered by the first modal
 * opening.
 */
final class CompanyYearPickerService
{
    public function __construct(
        private readonly CompanyListingService $companies,
        private readonly AvailableYearsResolver $years,
    ) {}

    /**
     * Active companies plus the selectable exercises, capped at the
     * current year: a generation shortcut never targets a future one.
     */
    public function build(): CompanyYearPickerData
    {
        return new CompanyYearPickerData(
            companies: array_values($this->companies->listForOptions()->items()),
            years: $this->years->yearsUpToCurrent(),
            currentYear: $this->years->currentYear(),
        );
    }
}
