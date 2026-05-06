<?php

declare(strict_types=1);

namespace App\Exceptions\Invoice;

use App\Exceptions\BaseAppException;

/**
 * Levée par {@see App\Actions\Invoice\GenerateInvoiceAction} quand une
 * facture existe déjà pour le couple `(company, year, month)`. Cohérent
 * avec la doctrine immuabilité (Phase 14.E V1.2) : pas de regénération
 * silencieuse en V1.2.
 *
 * L'utilisateur doit explicitement supprimer la facture existante (via
 * un endpoint dédié, hors scope V1.2) ou attendre le mois suivant.
 */
final class InvoiceAlreadyExistsException extends BaseAppException
{
    public static function forCompanyYearMonth(int $companyId, int $year, int $month): self
    {
        return new self(
            technicalMessage: "Invoice already exists for company #{$companyId} year {$year} month {$month}.",
            userMessage: sprintf(
                'Une facture a déjà été générée pour cette entreprise pour %02d/%d. Les factures émises sont immuables ; supprimez la facture existante avant de regénérer.',
                $month,
                $year,
            ),
        );
    }
}
