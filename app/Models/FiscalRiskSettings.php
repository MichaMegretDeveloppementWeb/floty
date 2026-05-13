<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FiscalRiskSettingsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Seuils paramétrables de la grille de détection des zones de risque
 * fiscal (Phase 11 D1, ADR-0015 § D7 rev. 1.1, refondu D5.10.Q ·
 * suppression de `lld_breaks_chain`). Singleton applicatif ·
 * `id=1` toujours, créé à la volée au premier accès.
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
     * Singleton applicatif : retourne l'unique ligne, création
     * automatique avec les valeurs par défaut au premier accès. Pattern
     * `firstOrCreate` atomique (cf. `BillingSettings::singleton`).
     *
     * Les défauts sont matérialisés ici (et non délégués aux `default()`
     * SQL des colonnes) pour rester portables : selon le driver (SQLite,
     * MySQL ≥8, etc.), un `INSERT` sans colonne explicite n'honore pas
     * forcément les défauts SQL. Source de vérité unique côté PHP.
     */
    public static function singleton(): self
    {
        return self::query()->firstOrCreate([], [
            'max_interval' => 15,
            'threshold_low' => 30,
            'threshold_high' => 90,
            'count_high' => 5,
        ]);
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
