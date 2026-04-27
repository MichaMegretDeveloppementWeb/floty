<?php

declare(strict_types=1);

namespace App\Enums\Declaration;

/**
 * Statut cycle de vie d'une déclaration fiscale annuelle.
 *
 * Transitions autorisées (cf. 02-schema-fiscal.md § 2) :
 *   - Draft      → Verified
 *   - Verified   → Generated   (génération d'un PDF ADR-0003)
 *   - Generated  → Sent        (utilisateur marque comme transmise)
 *   - *          → Draft       (retour en arrière possible, sans effacer les PDF)
 *
 * L'invalidation (`declarations.is_invalidated`) est **orthogonale** au
 * statut — une déclaration peut être invalidée dans n'importe quel statut.
 */
enum DeclarationStatus: string
{
    case Draft = 'draft';
    case Verified = 'verified';
    case Generated = 'generated';
    case Sent = 'sent';
}
