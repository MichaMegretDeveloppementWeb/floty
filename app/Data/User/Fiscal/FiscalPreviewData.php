<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Réponse de l'endpoint `POST /app/planning/preview-taxes` :
 * **coût fiscal standalone** d'une attribution (location/contrat).
 *
 * La LCD/LLD se calcule **contrat par contrat individuellement** ·
 * la durée du contrat seul détermine la qualification (≤ 30 j → LCD,
 * sinon LLD). Aucune notion de cumul annuel d'un couple véhicule ×
 * entreprise. Le preview répond donc strictement à la question :
 * « combien coûte fiscalement ce contrat précis ? ».
 */
#[TypeScript]
final class FiscalPreviewData extends Data
{
    public function __construct(
        public int $fiscalYear,
        /** Nombre de jours retenus par l'attribution (dans l'année). */
        public int $daysCount,
        /** Décomposition CO₂ + polluants + total + exonérations. */
        public FiscalBreakdownData $breakdown,
    ) {}
}
