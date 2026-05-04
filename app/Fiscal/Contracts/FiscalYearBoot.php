<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Fiscal\Year2024\Year2024Boot;
use App\Providers\FiscalServiceProvider;

/**
 * Définit le contrat d'enregistrement des règles fiscales d'une année
 * dans le {@see FiscalRuleRegistry}.
 *
 * **Pourquoi ce contrat** : avant ζ, le {@see FiscalServiceProvider}
 * appelait en dur `registerYear2024()` et listait les classes de règles
 * inline. Ajouter 2025 imposait de modifier le provider. Cette interface
 * inverse la dépendance : chaque année déclare ses propres règles dans
 * sa propre classe `Year{YYYY}Boot`, et le provider les découvre via
 * `config('floty.fiscal.year_boots')`.
 *
 * **Conventions** :
 * - Une implémentation par année (ex. {@see Year2024Boot}).
 * - `year()` retourne l'année civile de référence (ex. 2024).
 * - `rules()` retourne la liste des **classes** de règles (FQCN), pas des
 *   instances — la résolution est faite par le registry via le container.
 * - L'ordre n'est pas significatif côté registry, mais on garde l'ordre
 *   logique (Classification → Exemption → Pricing → Transversal) pour
 *   lisibilité (cf. `taxes-rules/2024.md`).
 *
 * **Hors scope** : les règles « architecturales » qui vivent hors pipeline
 * (R-2024-001, 007, 009, 020, 023, 024 — cf. ADR-0006 § 2 et docblock du
 * provider) ne sont pas listées ici. Elles sont gérées ailleurs dans
 * l'application.
 */
interface FiscalYearBoot
{
    /**
     * Année civile pour laquelle ces règles s'appliquent.
     */
    public function year(): int;

    /**
     * Liste des classes de règles fiscales à enregistrer pour cette
     * année. Les classes sont résolues par le container Laravel via
     * {@see FiscalRuleRegistry::rulesForYear()}.
     *
     * @return list<class-string<FiscalRule>>
     */
    public function rules(): array;
}
