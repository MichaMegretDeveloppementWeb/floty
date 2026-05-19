<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FiscalRiskSettingsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Configurable thresholds for the LCD fiscal risk detection grid (ADR-0015 § D7 rev. 1.1).
 * Application singleton (always `id=1`), auto-created with defaults on first access.
 *
 * @property int $id
 * @property int $max_interval
 * @property int $threshold_low
 * @property int $threshold_high
 * @property int $count_high
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'max_interval',
    'threshold_low',
    'threshold_high',
    'count_high',
])]
final class FiscalRiskSettings extends Model
{
    /** @use HasFactory<FiscalRiskSettingsFactory> */
    use HasFactory;

    protected $table = 'fiscal_risk_settings';

    /**
     * Returns the application singleton row, auto-creating it with defaults on first access.
     *
     * Defaults are materialized in PHP (not delegated to SQL column defaults) to stay
     * portable across drivers. Same race-safe pattern as {@see BillingSettings::singleton()}.
     */
    public static function singleton(): self
    {
        return self::unguarded(fn (): self => self::query()->firstOrCreate(['id' => 1], [
            'max_interval' => 15,
            'threshold_low' => 30,
            'threshold_high' => 90,
            'count_high' => 5,
        ]));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_interval' => 'integer',
            'threshold_low' => 'integer',
            'threshold_high' => 'integer',
            'count_high' => 'integer',
        ];
    }
}
