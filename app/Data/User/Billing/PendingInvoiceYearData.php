<?php

declare(strict_types=1);

namespace App\Data\User\Billing;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Synthèse 1-ligne par année des factures qui restent à générer pour
 * une entreprise (Phase D5.10.S · encart « À faire » Show Entreprise).
 *
 * Une « facture à générer » = mois où `daysUsed > 0` ET tarif présent
 * ET pas encore de row `Invoice` active sur le couple
 * `(company, year, month)`. On ne compte pas :
 *   - les mois sans contrat (rien à facturer)
 *   - les mois bloqués par tarif manquant (utilisateur doit d'abord
 *     renseigner le tarif véhicule · message porté par l'onglet
 *     Facturation, pas par cet encart)
 *   - les mois futurs (le mois en cours et au-delà ne sont pas
 *     facturables tant qu'ils ne sont pas écoulés)
 */
#[TypeScript]
final class PendingInvoiceYearData extends Data
{
    public function __construct(
        public int $fiscalYear,
        public int $missingInvoicesCount,
    ) {}
}
