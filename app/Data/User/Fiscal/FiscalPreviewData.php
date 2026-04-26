<?php

namespace App\Data\User\Fiscal;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Réponse de l'endpoint `POST /app/planning/preview-taxes` —
 * comparaison de l'état fiscal d'un couple AVANT vs APRÈS l'ajout
 * d'attributions.
 *
 * `before` est `null` quand le couple n'avait encore aucune
 * attribution dans l'année courante.
 */
#[TypeScript]
final class FiscalPreviewData extends Data
{
    public function __construct(
        public int $fiscalYear,
        public int $newDaysCount,
        public int $existingCumul,
        public int $futureCumul,
        public ?FiscalBreakdownData $before,
        public FiscalBreakdownData $after,
        public float $incrementalDue,
    ) {}
}
