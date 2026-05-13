<?php

declare(strict_types=1);

namespace App\Data\User\FiscalRiskSettings;

use App\Models\FiscalRiskSettings;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\GreaterThan;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Seuils paramétrables de la grille de détection des zones de risque
 * fiscal (Phase 11 D1, ADR-0015 § D7 rev. 1.1). Sert à la fois :
 *   - en sortie pour la page Paramètres (lecture)
 *   - en entrée HTTP (validation Spatie via `rules`)
 *
 * Tous les champs sont obligatoires : la règle métier impose une grille
 * complète. Les bornes minimales (`Min`) garantissent que les seuils
 * restent positifs et exploitables par le moteur de détection (D2).
 *
 * Contrainte d'ordre `threshold_high > threshold_low` garantie par
 * l'attribut `GreaterThan` ; le moteur de détection D2 s'appuie sur
 * cette invariance pour son arbre de classification.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class FiscalRiskSettingsData extends Data
{
    public function __construct(
        #[Min(1)]
        public int $maxInterval,
        #[Min(0)]
        public int $thresholdLow,
        #[Min(1), GreaterThan('threshold_low')]
        public int $thresholdHigh,
        #[Min(1)]
        public int $countHigh,
    ) {}

    public static function fromModel(FiscalRiskSettings $settings): self
    {
        return new self(
            maxInterval: $settings->max_interval,
            thresholdLow: $settings->threshold_low,
            thresholdHigh: $settings->threshold_high,
            countHigh: $settings->count_high,
        );
    }
}
