<?php

declare(strict_types=1);

namespace App\Enums\Invoice;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Reason a `(company, year, month)` is skipped during bulk invoice generation.
 */
#[TypeScript]
enum BulkInvoiceGenerationSkipReason: string
{
    /** No usage day on the month, nothing to bill. */
    case NoActivity = 'no_activity';

    /** Missing yearly pricing for at least one vehicle. */
    case MissingPricing = 'missing_pricing';

    /** Invoice already issued for this `(company, year, month)` triplet. */
    case AlreadyInvoiced = 'already_invoiced';

    /** Current or future month, not yet billable. */
    case NotPastMonth = 'not_past_month';
}
