<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

/**
 * Marker interface · règle fiscale documentaire-only (Phase 13 D5.11 ·
 * complément ADR-0022).
 *
 * **Pourquoi cette interface séparée** · certaines règles fiscales ne
 * participent **pas** au pipeline de calcul (`FiscalPipeline`) mais
 * portent une connaissance fiscale métier (cadre légal, principe
 * architectural, garde-fou UI). Exemples 2024 · R-2024-001 redevable,
 * R-2024-009 mise hors-service, R-2024-024 garde-fou Crit'Air, etc.
 *
 * **Avantage du contrat séparé** (vs flag booléen sur `FiscalRule`) ·
 * le typage statique interdit l'inclusion accidentelle d'une règle
 * documentaire dans `FiscalRuleRegistry::register()` (pipeline), qui
 * accepte uniquement `list<class-string<FiscalRule>>` sans inclure les
 * `InformativeRule`. Le pipeline reste donc strictement isolé.
 *
 * **Ce qu'elles partagent avec les règles pipeline** · les métadonnées
 * (code, nom, description, base légale enrichie d'URLs officielles,
 * type, taxes concernées, dates d'applicabilité, ordre d'affichage,
 * état actif). Toutes ces données vivent dans la classe PHP, conformes
 * à ADR-0022 (« classes PHP = source de vérité unique »).
 *
 * **Consommation** · uniquement par {@see Database\Seeders\FiscalRulesSeeder}
 * pour peupler la table-index `fiscal_rules` et alimenter la page
 * « Règles de calcul » (User/FiscalRules/Index).
 */
interface InformativeRule extends FiscalRule {}
