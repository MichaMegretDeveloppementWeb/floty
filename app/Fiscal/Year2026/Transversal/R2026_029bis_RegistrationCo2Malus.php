<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use Carbon\CarbonImmutable;

/**
 * R-2026-029-bis · Malus CO₂ à l'immatriculation · **version 01/09 →
 * 31/12/2026** (toilettage rédactionnel Ordo 2025-1247 art. 4).
 *
 * **Règle fiscale hors périmètre de l'application** · pendant de
 * {@see R2026_029_RegistrationCo2Malus} qui couvre 01/01-31/08/2026.
 *
 * **Scission ADR-0022 strict · toilettage RÉDACTIONNEL** ·
 * - L'article L. 421-62 a été modifié par l'Ordonnance n° 2025-1247 du
 *   17/12/2025 art. 4 (effet 01/09/2026 par art. 49).
 * - Les **barèmes 2026 et 2027 sont identiques** entre v 01/01-31/08
 *   et v 01/09 (seuil 108 g, plafond 80 000 € pour 2026 · seuil 103 g,
 *   plafond 90 000 € pour 2027).
 * - La scission est imposée par ADR-0022 strict (une version légale =
 *   une classe PHP), pas par un changement matériel.
 *
 * **Cohérence vs autres scissions Ordo 2025-1247** · le toilettage du
 * 01/09/2026 touche aussi L. 421-126 (R-2026-018/018-bis OIG CO₂),
 * L. 421-134 (R-2026-013/013-bis catégorisation polluants) et
 * L. 421-138 (R-2026-018/018-bis OIG polluants) selon le bilan Z0.
 * R-2026-029-bis est la 4e scission rédactionnelle Ordo 2025-1247
 * identifiée dans le périmètre fiscalité véhicules.
 *
 * **Audit Chrome live 15/05/2026** · L. 421-62 v 01/09/2026
 * (URL section LEGISCTA000044598969/2026-09-01) confirmé · « Version
 * en vigueur à partir du 01/09/2026 » « Modifié par Ordonnance
 * n°2025-1247 du 17 décembre 2025 - art. 4 ». Barèmes 2026 et 2027
 * strictement identiques à v 01/01-31/08/2026.
 *
 * **Marquée inactive** · règle fiscale réelle mais hors périmètre de
 * calcul de l'application (grisée dans la page Règles de calcul).
 */
final readonly class R2026_029bis_RegistrationCo2Malus implements InformativeRule
{
    public function ruleCode(): string
    {
        return 'R-2026-029-bis';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2026, 9, 1, 0, 0, 0);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2026, 12, 31, 23, 59, 59);
    }

    public function name(): string
    {
        return "Malus CO₂ à l'immatriculation (version 01/09 → 31/12/2026, toilettage Ordo 2025-1247)";
    }

    public function description(): string
    {
        return "Taxe ponctuelle acquittée par le titulaire du certificat d'immatriculation lors de la première immatriculation en France d'un véhicule de tourisme. Toilettage rédactionnel par Ordonnance n° 2025-1247 du 17/12/2025 art. 4 à effet du 01/09/2026 · paramètres et barèmes 2026 strictement identiques à la version précédente (seuil 108 g, plafond 80 000 €). Scission ADR-0022 obligatoire (une version légale = une classe PHP). Hors périmètre de l'application · le bailleur acquitte, jamais l'entreprise utilisatrice.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 29;
    }

    public function isActive(): bool
    {
        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-58 à L. 421-70-1 (section · v 01/09/2026)',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044598969/2026-09-01/',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-20-40',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13927-PGP.html/identifiant=BOI-AIS-MOB-10-20-40-20250604',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'ORDO',
                'reference' => 'Ordonnance n° 2025-1247 du 17 décembre 2025 · art. 4 (modification L. 421-62 rédactionnelle) + art. 49 (entrée en vigueur 01/09/2026)',
                'url' => 'https://www.legifrance.gouv.fr/jorf/id/JORFTEXT000053091516',
                'consulted_at' => '2026-05-15',
            ],
        ];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2];
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Connexe,
            section: RuleSection::TaxeConnexe,
            title: "Malus CO₂ à l'immatriculation (toilettage 01/09/2026)",
            pitch: 'Période 01/09-31/12/2026 · paramètres et barèmes identiques à R-2026-029. Toilettage rédactionnel par Ordo 2025-1247 art. 4 (effet art. 49).',
            body: "Même mécanique et mêmes paramètres que la période précédente (R-2026-029 v 01/01-31/08/2026) · seuil 108 g CO₂/km, plafond 80 000 €, suppression plafonnement 50 %. Aucun impact pratique sur le calcul · la scission est imposée par ADR-0022 strict du fait du changement de version de l'article porteur L. 421-62. Hors périmètre de l'application · le bailleur paie, l'entreprise utilisatrice ne paie pas directement.",
            example: 'Le même véhicule à 115 g CO₂/km immatriculé en septembre 2026 ou en août 2026 acquitte exactement le même malus (210 €). Seule la version légale de référence change · L. 421-62 v 01/03/2025-01/09/2026 (R-2026-029) ou L. 421-62 v 01/09/2026 (R-2026-029-bis).',
        );
    }
}
