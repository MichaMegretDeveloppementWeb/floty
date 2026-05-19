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
 * Configurable thresholds for the fiscal risk detection grid
 * (ADR-0015 § D7 rev. 1.1). Serves both the read settings page and
 * the HTTP write endpoint.
 *
 * All fields are required: the business rule expects a complete grid.
 * Minimums (`Min`) keep thresholds positive and usable by the detection
 * engine. The `GreaterThan` invariant `threshold_high > threshold_low`
 * is relied upon by the classification tree.
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
