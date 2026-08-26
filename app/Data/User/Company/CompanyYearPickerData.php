<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload of the "pick a company and an exercise" modal, the shortcut
 * entry point to invoice annexes and fiscal declarations.
 *
 * Served through `Inertia::optional()` and pulled by a partial reload
 * when the modal is first opened, so no screen pays for it at mount.
 */
#[TypeScript]
final class CompanyYearPickerData extends Data
{
    /**
     * @param  list<CompanyOptionData>  $companies  Active companies, ordered by legal name.
     * @param  list<int>  $years  Ascending range, reversed by the front-end for display.
     */
    public function __construct(
        public array $companies,
        public array $years,
        public int $currentYear,
    ) {}
}
