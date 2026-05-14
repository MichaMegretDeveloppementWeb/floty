<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Item « facture en attente de génération » affiché sur le dashboard
 * (Phase 13 D5.15). Une entrée par couple `(company, year)` qui a au
 * moins une facture mensuelle restant à générer.
 *
 * `missingInvoicesCount` est le nombre de mois éligibles à génération
 * (mois passés avec utilisation effective + tarif présent + pas
 * encore facturé). Affiché côté front comme « 3 factures à générer ».
 */
#[TypeScript]
final class DashboardPendingInvoiceItemData extends Data
{
    public function __construct(
        public int $companyId,
        public string $companyShortCode,
        public string $companyLegalName,
        public int $fiscalYear,
        public int $missingInvoicesCount,
    ) {}
}
