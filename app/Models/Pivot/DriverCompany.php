<?php

declare(strict_types=1);

namespace App\Models\Pivot;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Pivot Driver ↔ Company avec dates d'entrée et de sortie.
 *
 * Cf. Phase 06 V1.2 - un conducteur peut appartenir à plusieurs entreprises
 * au cours du temps. Chaque membership porte sa propre période d'activité.
 *
 * @property int $id
 * @property int $driver_id
 * @property int $company_id
 * @property Carbon $joined_at
 * @property Carbon|null $left_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class DriverCompany extends Pivot
{
    protected $table = 'driver_company';

    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'left_at' => 'date',
        ];
    }

    /**
     * Le membership est-il actif à la date donnée ?
     */
    public function isActiveAt(Carbon $date): bool
    {
        if ($this->joined_at->greaterThan($date)) {
            return false;
        }

        if ($this->left_at !== null && $this->left_at->lessThan($date)) {
            return false;
        }

        return true;
    }

    /**
     * Le membership couvre-t-il entièrement la période [start, end] ?
     */
    public function coversPeriod(Carbon $start, Carbon $end): bool
    {
        if ($this->joined_at->greaterThan($start)) {
            return false;
        }

        if ($this->left_at !== null && $this->left_at->lessThan($end)) {
            return false;
        }

        return true;
    }
}
