<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use Carbon\CarbonImmutable;

/**
 * Interface base de toute règle fiscale Floty (cf. ADR-0006 § 1).
 *
 * Chaque règle réelle implémente l'un des 5 sous-types :
 *   - {@see ClassificationRule} (qualification d'une caractéristique)
 *   - {@see PricingRule}        (tarif annuel plein)
 *   - {@see ExemptionRule}      (court-circuit conditionnel)
 *   - {@see AbatementRule}      (modification d'entrée avant tarif)
 *   - {@see TransversalRule}    (prorata, arrondi, indispos…)
 *
 * `ruleCode()` est l'identifiant publié dans `taxes-rules/{year}.md`
 * (format `R-{year}-{nnn}`). Il est immuable (cf. ADR-0009) et apparaît
 * dans les snapshots PDF + la page « Règles de calcul ».
 *
 * **Granularité temporelle (chantier κ)** : chaque règle déclare sa
 * période d'applicabilité via `applicabilityStart()` / `applicabilityEnd()`.
 * La grande majorité des règles couvrent l'année fiscale entière et
 * peuvent adopter {@see App\Fiscal\Contracts\Concerns\AnnualRuleTrait}.
 * Les règles partielles (apparition ou disparition en cours d'année)
 * implémentent ces méthodes directement.
 *
 * **Métadonnées (chantier κ.6 / ADR-0022)** : les classes PHP sont la
 * source de vérité de toute la métadonnée d'une règle (nom, description,
 * type fiscal, ordre d'affichage, base légale, état actif). La table
 * `fiscal_rules` n'est qu'un index miroir synchronisé par
 * {@see Database\Seeders\FiscalRulesSeeder} via ces accesseurs.
 */
interface FiscalRule
{
    public function ruleCode(): string;

    /**
     * Taxes concernées : `[Co2]`, `[Pollutants]` ou les deux. Permet
     * au pipeline de filtrer les règles selon la taxe en cours
     * d'évaluation.
     *
     * @return list<TaxType>
     */
    public function taxesConcerned(): array;

    /**
     * Date à partir de laquelle la règle est applicable (incluse). Pour
     * une règle annuelle, c'est `{year}-01-01 00:00:00`.
     */
    public function applicabilityStart(): CarbonImmutable;

    /**
     * Date jusqu'à laquelle la règle est applicable (incluse). Pour une
     * règle annuelle, c'est `{year}-12-31 23:59:59`. `null` signifie
     * « open-ended » : règle valide depuis `applicabilityStart()`
     * jusqu'à indéfini.
     */
    public function applicabilityEnd(): ?CarbonImmutable;

    /**
     * Nom court de la règle (apparaît en titre dans la page « Règles de
     * calcul » et dans les snapshots PDF).
     */
    public function name(): string;

    /**
     * Description longue (multi-paragraphes possibles, formatage texte
     * brut). Affichée en accordéon sur la page Règles.
     */
    public function description(): string;

    /**
     * Sous-type fonctionnel - détermine le rôle de la règle dans le
     * pipeline (ADR-0006 § 1).
     */
    public function ruleType(): RuleType;

    /**
     * Ordre d'affichage stable dans la page Règles. Une règle ajoutée
     * en cours d'année fiscale prend le `displayOrder` suivant.
     */
    public function displayOrder(): int;

    /**
     * Base légale structurée. Chaque entrée est un tableau associatif
     * dont les clés varient selon le `type` (CIBS / CGI / BOFIP /
     * NOTICE). Format conforme au stockage JSON `fiscal_rules.legal_basis`.
     *
     * Le shape `array{...}` est repris par Spatie Data → TS Transformer
     * pour générer un type TypeScript structuré côté frontend (au lieu
     * du fallback `Record<string, any>` quand le shape est `mixed`).
     *
     * @return list<array{type: string, article?: string, reference?: string, paragraph?: string, url?: string, consulted_at?: string}>
     */
    public function legalBasis(): array;

    /**
     * Vrai si la règle est active (opérante et publiée). Les règles
     * inactives sont conservées en BDD pour rester référencées par les
     * snapshots PDF historiques. Le trait
     * {@see App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait}
     * fournit le défaut `true` ; les règles désactivées implémentent
     * directement `isActive(): bool { return false; }`.
     */
    public function isActive(): bool;

    /**
     * Contenu pédagogique riche (titre, pitch, body, exemple,
     * barèmes formatés) affiché sur la page « Règles de calcul »
     * (User/FiscalRules/Index · cf. {@see RulePedagogicalContent}).
     *
     * **Phase 13 D5.12 (ADR-0022 finalisée v1.2)** · ce contenu
     * vivait initialement dans `resources/js/data/fiscalRulesContent.ts`
     * (couche TS séparée). Désormais migré dans chaque classe Rule
     * pour conformité doctrine « 1 règle = 1 classe PHP, source de
     * vérité unique, y compris pour le contenu pédagogique ».
     */
    public function pedagogicalContent(): RulePedagogicalContent;
}
