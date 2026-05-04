<?php

declare(strict_types=1);

namespace App\Enums\Contract;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Type de contrat de location, qualification métier (cf. ADR-0014).
 *
 * - `lcd` (Location de Courte Durée) : contrat dont la **durée**
 *   ≤ 30 jours consécutifs OU ≤ 1 mois civil entier. L'algorithme
 *   d'évaluation `is_short_term_rental` (cf. R-2024-021 v2.0) vérifie
 *   ces critères ; le `contract_type` côté DB reste un libellé indicatif
 *   posé par l'utilisateur - l'exonération fiscale est calculée
 *   indépendamment, à partir des dates.
 * - `lld` (Location de Longue Durée) : contrat hors périmètre LCD,
 *   typique des usages permanents.
 * - `mise_a_disposition_assimilee` : autres formes de mise à disposition
 *   (prêt, partage d'utilisation, etc., cf. CIBS art. L. 421-99 et
 *   BOFiP § 130-150).
 */
#[TypeScript]
enum ContractType: string
{
    case Lcd = 'lcd';
    case Lld = 'lld';
    case MiseADispositionAssimilee = 'mise_a_disposition_assimilee';

    public function label(): string
    {
        return match ($this) {
            self::Lcd => 'Location de courte durée (LCD)',
            self::Lld => 'Location de longue durée (LLD)',
            self::MiseADispositionAssimilee => 'Mise à disposition assimilée',
        };
    }
}
