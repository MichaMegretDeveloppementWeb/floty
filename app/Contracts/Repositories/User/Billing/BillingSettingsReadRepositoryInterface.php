<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Billing;

use App\Models\BillingSettings;

/**
 * Lecture de l'émetteur de facture (Phase 14.G V1.2 · singleton).
 */
interface BillingSettingsReadRepositoryInterface
{
    /**
     * Retourne l'unique row (création automatique si table vide).
     */
    public function get(): BillingSettings;
}
