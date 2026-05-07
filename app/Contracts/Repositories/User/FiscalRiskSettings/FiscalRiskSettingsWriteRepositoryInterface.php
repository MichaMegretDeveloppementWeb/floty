<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\FiscalRiskSettings;

use App\Data\User\FiscalRiskSettings\FiscalRiskSettingsData;
use App\Models\FiscalRiskSettings;

/**
 * Écriture des seuils de détection de risque fiscal (Phase 11 D1,
 * singleton ADR-0015 § D7 rev. 1.1).
 */
interface FiscalRiskSettingsWriteRepositoryInterface
{
    /**
     * Met à jour la ligne singleton avec les valeurs du DTO. Crée la
     * ligne au premier appel si elle n'existait pas encore.
     */
    public function update(FiscalRiskSettingsData $data): FiscalRiskSettings;
}
