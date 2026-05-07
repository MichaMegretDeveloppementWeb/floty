<?php

declare(strict_types=1);

namespace App\Enums\Invoice;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Cible de redirection après régénération d'une facture (Phase 14.O).
 *
 * Le client (composant Vue qui déclenche le POST regenerate) connaît son
 * contexte de navigation et passe la cible explicitement, plutôt que de
 * laisser le backend deviner via le header `Referer` (anti-pattern non
 * testable et fragile vis-à-vis de Wayfinder).
 *
 * - `Show` : redirect vers la page détail de la nouvelle facture.
 *   Utile depuis Show facture courante (l'ID a changé après regen).
 * - `Index` : redirect vers la liste des factures.
 *   Utile depuis Index factures.
 * - `CompanyTab` : redirect vers la fiche entreprise, onglet Facturation.
 *   Utile depuis Company.show?tab=billing où l'utilisateur veut rester.
 */
#[TypeScript]
enum RegenerateRedirectTarget: string
{
    case Show = 'show';
    case Index = 'index';
    case CompanyTab = 'company-tab';
}
