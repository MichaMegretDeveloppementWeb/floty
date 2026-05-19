<?php

declare(strict_types=1);

namespace App\Enums\Invoice;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum BulkInvoiceGenerationFailureReason: string
{
    /** Tarif annuel manquant pour au moins un véhicule du mois. */
    case MissingPricing = 'missing_pricing';

    /**
     * Race condition · une autre génération concurrente a posé une
     * facture sur ce mois entre la résolution de la liste et l'appel
     * `GenerateInvoiceAction`. Géré comme un skip a posteriori côté UI.
     */
    case AlreadyExists = 'already_exists';

    /**
     * Garde-fou refusant la génération sur un mois non écoulé. Normalement
     * filtré en amont par le resolver, ce cas couvre les régressions UI
     * ou les appels forgés.
     */
    case NotPastMonth = 'not_past_month';

    /** Toute autre exception non typée (bug applicatif, panne PDF, etc.). */
    case Unexpected = 'unexpected';
}
