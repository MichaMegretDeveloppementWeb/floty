<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2025-033 · ⚠️ NOUVEAUTÉ 2025 · Taxe Annuelle Incitative au
 * verdissement des flottes (TAI).
 *
 * **Règle documentaire-only · HORS PÉRIMÈTRE FLOTY V1 · n'existe pas
 * en 2024 (création LF 2025 art. 95).**
 *
 * Nouvelle taxe annuelle visant à inciter les grandes flottes à verdir
 * leur parc · pour chaque véhicule à faibles émissions (VFE) manquant
 * pour atteindre un quota cible, un tarif unitaire est dû.
 *
 * **Paramètres 2025** (première année d'application) :
 * - Quota cible · **15 % de VFE** dans la flotte.
 * - Tarif unitaire · **2 000 €/véhicule manquant**.
 * - Période d'application · **01/03/2025 → 31/12/2025** (306 jours,
 *   facteur de prorata 1/306e par dérogation au b du 1° de L. 421-132-6
 *   · note d'application V de LF 2025 art. 28).
 * - Déclaration · janvier 2026.
 *
 * **Redevable** · entreprise disposant d'une flotte **≥ 100 véhicules**
 * sur l'année civile (au sens TVA · CGI art. 256 A et 256 B).
 *
 * **Pourquoi hors périmètre Floty V1** :
 * - Les entreprises utilisatrices Floty prises individuellement disposent
 *   de portions de flotte bien inférieures à 100 véhicules.
 * - Le seuil pourrait être atteint au niveau du bailleur (société de
 *   location) · mais celui-ci n'est pas dans le périmètre de calcul Floty.
 *
 * **Note implementation** · classe documentaire-only, inscrite dans
 * `Year2025Boot::informativeRules()`. N'existe pas dans Year2024Boot.
 */
final readonly class R2025_033_FleetGreeningIncentiveTax implements InformativeRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2025-033';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return 'Taxe Annuelle Incitative au verdissement des flottes (TAI) - nouveauté 2025';
    }

    public function description(): string
    {
        return "Nouvelle taxe annuelle créée par LF 2025 art. 95 (CIBS art. L. 421-132-1 à L. 421-132-6) visant à inciter les grandes flottes (≥ 100 véhicules) à verdir leur parc. Pour 2025 (première année d'application) · quota cible 15 % de véhicules à faibles émissions (VFE), tarif unitaire 2 000 €/véhicule manquant pour atteindre le quota. Période d'application fractionnée · 01/03/2025 → 31/12/2025 (306 jours, facteur de prorata 1/306e par dérogation au b du 1° de L. 421-132-6 · note d'application V de LF 2025 art. 28). Déclaration en janvier 2026. Hors périmètre Floty V1 · le seuil de 100 véhicules n'est pas atteint par les entreprises utilisatrices Floty prises individuellement · au niveau du bailleur, ce dernier n'est pas dans le périmètre de calcul Floty.";
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
                'article' => 'L. 421-132-1 à L. 421-132-6 (paragraphe 3 bis créé par LF 2025 art. 95)',
                'url' => 'https://www.legifrance.gouv.fr/codes/id/LEGISCTA000051214904',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'LOI',
                'reference' => 'LF 2025 art. 95 + art. 28 V (note d\'application prorata 1/306e)',
                'url' => 'https://www.legifrance.gouv.fr/loda/id/LEGITEXT000051214900',
                'consulted_at' => '2026-05-14',
            ],
        ];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        // Taxe distincte des deux taxes annuelles Floty (CO₂ d'affectation
        // et polluants). Déclarée pour exhaustivité, non rattachée à
        // TaxType::Co2 ni TaxType::Pollutants. On utilise TaxType::Co2
        // pour satisfaire le contrat (`taxesConcerned()` non vide).
        return [TaxType::Co2];
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Cadre,
            section: RuleSection::TaxeConnexe,
            title: 'Taxe Annuelle Incitative au verdissement des flottes (TAI)',
            pitch: 'Nouvelle taxe 2025 visant à inciter les flottes de 100 véhicules ou plus à augmenter leur part de véhicules à faibles émissions.',
            body: "Créée par la LF 2025 (art. 95) et codifiée aux articles L. 421-132-1 à L. 421-132-6 du CIBS. Pour 2025, première année d'application, le quota cible est de 15 % de VFE dans la flotte, avec un tarif de 2 000 € par véhicule manquant pour atteindre ce quota. Période fractionnée 01/03/2025 → 31/12/2025 (306 jours, facteur de prorata 1/306e). Hors périmètre Floty V1 · le seuil de 100 véhicules par redevable n'est pas atteint par les entreprises utilisatrices Floty prises individuellement, et le bailleur n'est pas dans le périmètre de calcul de l'application. Documentée pour exhaustivité fiscale.",
            example: 'Une entreprise disposant de 200 véhicules dont 20 VFE (10 %) en 2025 · quota cible 15 % = 30 VFE · 10 véhicules manquants · taxe = 10 × 2 000 € × 306/306 = 20 000 €. Déclaration en janvier 2026.',
        );
    }
}
