<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2025-033 - 2025 new feature: annual incentive tax for the
 * acquisition of low-emission light vehicles ("TAI").
 *
 * Documentation-only rule, OUT OF FLOTY V1 SCOPE. Does not exist in
 * 2024 (created by LF 2025 art. 95, CIBS paragraph 3 bis).
 *
 * Official legal title: "Tarif de la taxe annuelle incitative relative
 * à l'acquisition de véhicules légers à faibles émissions" (Légifrance,
 * paragraph 3 bis, articles L. 421-132-1 to L. 421-132-6, CIBS, in
 * force since 01/03/2025 via LOI n°2025-127 du 14 février 2025).
 *
 * L. 421-132-2 legal formula: the amount due by each user company per
 * civil year is the product of three factors:
 *   1° Annual rate: 2 000 € in 2025, 4 000 € in 2026, 5 000 € from 2027.
 *   2° Gap to the target VFE integration objective, expressed as the
 *      number of vehicles missing to reach the target quota.
 *   3° Annual renewal rate = number of vehicles entering the fleet /
 *      total taxable fleet size (cf. L. 421-132-6).
 *
 * VFE integration target quotas: 15% (2025), 18% (2026), 25% (2027),
 * 48% (2030).
 *
 * 2025 (first year of application): 01/03/2025 → 31/12/2025 (306 days,
 * 1/306th prorata factor by derogation from b of 1° of L. 421-132-6
 * per application note V of LF 2025 art. 28). Declaration in January
 * 2026.
 *
 * Taxpayer: each user company (within the meaning of CIBS L. 421-98),
 * WITHOUT minimum fleet threshold in the current legislative text.
 *
 * Out of Floty V1 scope:
 *   - depends on data outside Floty (total fleet held by the company
 *     within the meaning of tax law, number of integrated VFEs, annual
 *     renewal rate) which Floty does not measure on its own.
 *   - hits the user company globally, whereas Floty computes per pair
 *     (vehicle, user company) on the vehicles of the shared rental
 *     fleet.
 *   - documented here for fiscal exhaustiveness; each user company's
 *     accountant evaluates exigibility.
 */
final readonly class R2025_033_FleetGreeningIncentiveTax implements InformativeRule
{
    use AnnualRuleTrait;

    public function ruleCode(): string
    {
        return 'R-2025-033';
    }

    public function isActive(): bool
    {
        return false;
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return "Taxe annuelle incitative relative à l'acquisition de véhicules légers à faibles émissions (TAI) - nouveauté 2025";
    }

    public function description(): string
    {
        return "Nouvelle taxe annuelle créée par LF 2025 art. 95 (CIBS art. L. 421-132-1 à L. 421-132-6) frappant chaque entreprise affectataire qui n'atteint pas un quota cible d'intégration de véhicules à faibles émissions (VFE) dans sa flotte. Formule légale (L. 421-132-2) · Montant = Tarif annuel × Écart à l'objectif cible × Taux annuel de renouvellement. Tarif annuel · 2 000 € en 2025, 4 000 € en 2026, 5 000 € dès 2027. Quotas cibles · 15 % (2025), 18 % (2026), 25 % (2027), 48 % (2030). Période 2025 fractionnée · 01/03/2025 → 31/12/2025 (306 jours, prorata 1/306e par dérogation au b du 1° de L. 421-132-6 · note d'application V de LF 2025 art. 28). Déclaration en janvier 2026. **Pas de seuil minimum de flotte dans le texte légal** · toute entreprise affectataire est concernée en principe. Hors périmètre V1 de l'application · cette taxe dépend de données globales de la flotte de l'entreprise utilisatrice (flotte totale, VFE entrants, taux de renouvellement) que l'application ne couvre pas. Documentée pour exhaustivité, le comptable évaluera l'éventuelle exigibilité.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 33;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-132-1 à L. 421-132-6',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000051187919',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'LOI',
                'reference' => 'LF 2025 art. 95 + 28 V',
                'url' => 'https://www.legifrance.gouv.fr/jorf/id/JORFTEXT000051168007',
                'consulted_at' => '2026-05-14',
            ],
        ];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        // Tax distinct from the two annual Floty taxes (assignment CO₂
        // and pollutants). Declared for exhaustiveness, not actually
        // tied to TaxType::Co2 or TaxType::Pollutants. TaxType::Co2 is
        // used to satisfy the non-empty `taxesConcerned()` contract.
        return [TaxType::Co2];
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::HorsPerimetre,
            section: RuleSection::TaxeConnexe,
            title: "Taxe annuelle incitative à l'acquisition de VFE (TAI)",
            pitch: "Nouvelle taxe 2025 qui frappe les entreprises affectataires n'atteignant pas un quota cible d'intégration de véhicules à faibles émissions (VFE) dans leur flotte.",
            body: "Créée par la LF 2025 (art. 95) et codifiée aux articles L. 421-132-1 à L. 421-132-6 du CIBS (paragraphe 3 bis). Formule légale = Tarif annuel × Écart à l'objectif × Taux annuel de renouvellement. Tarif annuel progressif · 2 000 € en 2025, 4 000 € en 2026, 5 000 € dès 2027. Quotas cibles VFE · 15 % (2025), 18 % (2026), 25 % (2027), 48 % (2030). Période 2025 fractionnée 01/03 → 31/12 (306 jours, prorata 1/306e). Pas de seuil minimum de flotte dans le texte légal actuel · toute entreprise affectataire est en principe concernée. Hors périmètre V1 de l'application · la taxe dépend de données globales de la flotte de l'entreprise utilisatrice (flotte totale, VFE entrants, taux de renouvellement annuel) que l'application ne mesure pas. Documentée ici pour exhaustivité du panorama fiscal · chaque comptable d'entreprise utilisatrice évaluera son exigibilité.",
            example: 'Entreprise affectataire 2025 · flotte taxable 200 véhicules dont 20 VFE (10 %). Quota cible 2025 = 15 %, soit 30 VFE attendus. Écart = 30 - 20 = 10 véhicules. Taux de renouvellement de la flotte = 40 entrées / 200 = 20 %. Montant TAI = 2 000 € × 10 × 0,20 = 4 000 € (annuel, à proratiser par 306/306 en 2025). Cet exemple illustre la mécanique légale L. 421-132-2 · le « tarif unitaire par véhicule manquant » est modulé par le taux de renouvellement, qui peut diviser ou multiplier la taxe.',
        );
    }
}
