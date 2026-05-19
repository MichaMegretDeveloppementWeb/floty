<?php

declare(strict_types=1);

namespace App\Exceptions\Invoice;

use App\Exceptions\BaseAppException;

/**
 * Invoice already exists for the given `(company, year, month)` triplet.
 * Aligned with the immutability doctrine: no silent regeneration.
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
