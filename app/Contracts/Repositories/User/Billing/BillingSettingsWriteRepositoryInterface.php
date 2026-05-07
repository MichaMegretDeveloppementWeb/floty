<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Billing;

use App\Data\User\Billing\BillingSettingsData;
use App\Models\BillingSettings;

/**
 * Mise à jour de l'émetteur de facture (Phase 14.G V1.2 · singleton).
 */
interface BillingSettingsWriteRepositoryInterface
{
    /**
     * Met à jour la ligne singleton avec les valeurs du DTO. Crée la
     * ligne au premier appel si elle n'existait pas encore.
     */
    public function update(BillingSettingsData $data): BillingSettings;
}
