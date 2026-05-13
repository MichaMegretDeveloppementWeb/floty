<?php

declare(strict_types=1);

namespace App\Enums\FiscalDeclaration;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Statut applicatif d'une déclaration fiscale (ADR-0015 § D4 rev. 1.1).
 *
 *  - `draft` : en cours de revue, peut être éditée.
 *  - `deferred` : marqueur visuel volontaire posé par l'utilisateur
 *    pour mémoriser une déclaration mise de côté en attente de
 *    consultation EC. Sémantiquement c'est **un draft**, donc la
 *    génération du PDF reste autorisée depuis ce statut (Phase 13
 *    D5.10.Z).
 *  - `generated` : PDF annexe produit, snapshot immuable. Peut devenir
 *    obsolète (cf. flag `is_obsolete` orthogonal) mais ne revient
 *    jamais en `draft`.
 *
 * Le statut `archived` (rev. 1.0) a été retiré : la traçabilité
 * historique est désormais portée par le couple `is_obsolete` +
 * `superseded_by_id`.
 */
#[TypeScript]
enum FiscalDeclarationStatus: string
{
    case Draft = 'draft';
    case Deferred = 'deferred';
    case Generated = 'generated';
}
