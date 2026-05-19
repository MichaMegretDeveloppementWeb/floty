<?php

declare(strict_types=1);

namespace App\Enums\Invoice;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum BulkInvoiceGenerationSkipReason: string
{
    /** Aucun jour utilisé sur le mois · rien à facturer. */
    case NoActivity = 'no_activity';

    /** Tarif annuel manquant pour au moins un véhicule. */
    case MissingPricing = 'missing_pricing';

    /** Annexe déjà émise sur le couple `(entreprise, année, mois)`. */
    case AlreadyInvoiced = 'already_invoiced';

    /** Mois en cours ou futur · non facturable tant que pas écoulé. */
    case NotPastMonth = 'not_past_month';
}
