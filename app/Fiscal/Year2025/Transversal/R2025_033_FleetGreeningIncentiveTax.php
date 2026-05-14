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
 * R-2025-033 · ⚠️ NOUVEAUTÉ 2025 · Taxe annuelle incitative relative à
 * l'acquisition de véhicules légers à faibles émissions (« TAI »).
 *
 * **Règle documentaire-only · HORS PÉRIMÈTRE FLOTY V1 · n'existe pas
 * en 2024 (création LF 2025 art. 95, paragraphe 3 bis du CIBS).**
 *
 * **Titre légal officiel** · « Tarif de la taxe annuelle incitative
 * relative à l'acquisition de véhicules légers à faibles émissions »
 * (Légifrance, paragraphe 3 bis, articles L. 421-132-1 à L. 421-132-6,
 * code des impositions sur les biens et services, en vigueur depuis le
 * 01/03/2025 par la LOI n°2025-127 du 14 février 2025).
 *
 * **Formule légale L. 421-132-2** · le montant dû par chaque entreprise
 * affectataire et chaque année civile est le produit de trois facteurs :
 *   1° **Tarif annuel** · 2 000 € en 2025, 4 000 € en 2026, 5 000 € à
 *      partir de 2027.
 *   2° **Écart à l'objectif cible d'intégration** de véhicules à
 *      faibles émissions (VFE), exprimé en nombre de véhicules manquants
 *      pour atteindre le quota cible.
 *   3° **Taux annuel de renouvellement** = nombre de véhicules entrés
 *      dans la flotte / taille totale de la flotte taxable (cf. L.
 *      421-132-6).
 *
 * **Quotas cibles d'intégration VFE** · 15 % (2025), 18 % (2026), 25 %
 * (2027), 48 % (2030).
 *
 * **Période 2025 (première année d'application)** · 01/03/2025 →
 * 31/12/2025 (306 jours, facteur de prorata 1/306e par dérogation
 * au b du 1° de L. 421-132-6 selon note d'application V de LF 2025 art.
 * 28). Déclaration en janvier 2026.
 *
 * **Redevable** · chaque entreprise affectataire (au sens CIBS L. 421-98),
 * **SANS seuil minimum de flotte** dans le texte législatif actuel.
 *
 * **Pourquoi hors périmètre Floty V1** · cette taxe :
 *   - dépend de données hors périmètre Floty (flotte totale détenue par
 *     l'entreprise au sens fiscal, nombre de VFE intégrés, taux de
 *     renouvellement annuel) que Floty ne mesure pas en propre.
 *   - frappe l'entreprise utilisatrice au global, alors que Floty
 *     calcule par couple (véhicule, entreprise utilisatrice) sur les
 *     véhicules de la flotte partagée de location (modèle Renaud).
 *   - documentée ici pour exhaustivité fiscale · le comptable de chaque
 *     entreprise utilisatrice évaluera lui-même son éventuelle exigibilité.
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
        return "Nouvelle taxe annuelle créée par LF 2025 art. 95 (CIBS art. L. 421-132-1 à L. 421-132-6) frappant chaque entreprise affectataire qui n'atteint pas un quota cible d'intégration de véhicules à faibles émissions (VFE) dans sa flotte. Formule légale (L. 421-132-2) · Montant = Tarif annuel × Écart à l'objectif cible × Taux annuel de renouvellement. Tarif annuel · 2 000 € en 2025, 4 000 € en 2026, 5 000 € dès 2027. Quotas cibles · 15 % (2025), 18 % (2026), 25 % (2027), 48 % (2030). Période 2025 fractionnée · 01/03/2025 → 31/12/2025 (306 jours, prorata 1/306e par dérogation au b du 1° de L. 421-132-6 · note d'application V de LF 2025 art. 28). Déclaration en janvier 2026. **Pas de seuil minimum de flotte dans le texte légal** · toute entreprise affectataire est concernée en principe. Hors périmètre Floty V1 · cette taxe dépend de données globales de la flotte de l'entreprise utilisatrice (flotte totale, VFE entrants, taux de renouvellement) que Floty ne couvre pas. Documentée pour exhaustivité, le comptable évaluera l'éventuelle exigibilité.";
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
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000051187919',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'LOI',
                'reference' => 'LOI n°2025-127 du 14 février 2025 art. 95 (création TAI) + art. 28 V (note d\'application prorata 1/306e en 2025)',
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
        // Taxe distincte des deux taxes annuelles Floty (CO₂ d'affectation
        // et polluants). Déclarée pour exhaustivité, non rattachée à
        // TaxType::Co2 ni TaxType::Pollutants. On utilise TaxType::Co2
        // pour satisfaire le contrat (`taxesConcerned()` non vide).
        return [TaxType::Co2];
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Connexe,
            section: RuleSection::TaxeConnexe,
            title: "Taxe annuelle incitative à l'acquisition de VFE (TAI)",
            pitch: "Nouvelle taxe 2025 qui frappe les entreprises affectataires n'atteignant pas un quota cible d'intégration de véhicules à faibles émissions (VFE) dans leur flotte.",
            body: "Créée par la LF 2025 (art. 95) et codifiée aux articles L. 421-132-1 à L. 421-132-6 du CIBS (paragraphe 3 bis). Formule légale = Tarif annuel × Écart à l'objectif × Taux annuel de renouvellement. Tarif annuel progressif · 2 000 € en 2025, 4 000 € en 2026, 5 000 € dès 2027. Quotas cibles VFE · 15 % (2025), 18 % (2026), 25 % (2027), 48 % (2030). Période 2025 fractionnée 01/03 → 31/12 (306 jours, prorata 1/306e). Pas de seuil minimum de flotte dans le texte légal actuel · toute entreprise affectataire est en principe concernée. Hors périmètre Floty V1 · la taxe dépend de données globales de la flotte de l'entreprise utilisatrice (flotte totale, VFE entrants, taux de renouvellement annuel) que l'application ne mesure pas. Documentée ici pour exhaustivité du panorama fiscal · chaque comptable d'entreprise utilisatrice évaluera son exigibilité.",
            example: 'Entreprise affectataire 2025 · flotte taxable 200 véhicules dont 20 VFE (10 %). Quota cible 2025 = 15 %, soit 30 VFE attendus. Écart = 30 - 20 = 10 véhicules. Taux de renouvellement de la flotte = 40 entrées / 200 = 20 %. Montant TAI = 2 000 € × 10 × 0,20 = 4 000 € (annuel, à proratiser par 306/306 en 2025). Cet exemple illustre la mécanique légale L. 421-132-2 · le « tarif unitaire par véhicule manquant » est modulé par le taux de renouvellement, qui peut diviser ou multiplier la taxe.',
        );
    }
}
