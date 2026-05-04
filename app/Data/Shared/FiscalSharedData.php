<?php

declare(strict_types=1);

namespace App\Data\Shared;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Bloc `fiscal` des shared props Inertia - année courante + années
 * disponibles. Source unique de vérité côté front pour
 * `useFiscalYear()`.
 */
#[TypeScript]
final class FiscalSharedData extends Data
{
    /**
     * @param  list<int>  $availableYears
     */
    public function __construct(
        public int $currentYear,
        public array $availableYears,
    ) {}
}
