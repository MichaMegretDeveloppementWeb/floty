<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\FiscalRiskSettings;

use App\Models\FiscalRiskSettings;

/**
 * Lecture des seuils de détection de risque fiscal (Phase 11 D1,
 * singleton ADR-0015 § D7 rev. 1.1).
 */
interface FiscalRiskSettingsReadRepositoryInterface
{
    /**
     * Retourne l'unique row (création automatique si table vide).
     */
    public function get(): FiscalRiskSettings;
}
