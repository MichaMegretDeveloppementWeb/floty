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
 * R-2026-031 · Taxes liées au certificat d'immatriculation · **version
 * 01/01 → 28/02/2026** (régime stabilisé LF 2025).
 *
 * **Règle fiscale hors périmètre de l'application** · trois taxes
 * ponctuelles acquittées à l'établissement d'une carte grise · taxe
 * régionale Y1 (CIBS L. 421-41 à L. 421-54), taxe Y2 (L. 421-55 à
 * L. 421-57, formation transport), taxe fixe Y4 (L. 421-49, 11 €).
 *
 * **Période 01/01-28/02/2026** · régime hérité de R-2025-031-bis
 * (exonération régionale Y1 facultative pour VE/H₂, paramètres LF 2025
 * art. 30). La période 01/03-31/12/2026 (création de L. 421-54-1 par
 * LF 2026 art. 60 · majoration IDF jusqu'à +13 €) est portée par
 * {@see R2026_031bis_RegistrationCardTaxes}.
 *
 * **Audit Chrome live 15/05/2026** · section LEGISCTA000044599003
 * v 2026-01-01 confirmée stable. L. 421-43 « En vigueur depuis le
 * 01/01/2022 ». Aucun article L. 421-54-1 visible dans cette version.
 *
 * **Marquée inactive** · règle fiscale réelle mais hors périmètre de
 * calcul de l'application (le bailleur paie, pas l'entreprise
 * utilisatrice).
 */
final readonly class R2026_031_RegistrationCardTaxes implements InformativeRule
{
    public function ruleCode(): string
    {
        return 'R-2026-031';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2026, 1, 1, 0, 0, 0);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2026, 2, 28, 23, 59, 59);
    }

    public function name(): string
    {
        return "Taxes liées au certificat d'immatriculation (version 01/01 → 28/02/2026)";
    }

    public function description(): string
    {
        return "Trois taxes ponctuelles acquittées lors de l'établissement de la carte grise · taxe régionale Y1, taxe Y2 (formation transport routier, VUL et camions), taxe fixe Y4 (11 €). Période 01/01-28/02/2026 · régime hérité de R-2025-031-bis (exonération régionale Y1 facultative par région pour VE/H₂ depuis LF 2025 art. 30). Hors périmètre de l'application · le bailleur immatricule les véhicules et acquitte ces taxes · les entreprises utilisatrices ne sont jamais directement redevables. La période 01/03-31/12/2026 (création de L. 421-54-1 par LF 2026 art. 60 · majoration IDF) est portée par R-2026-031-bis.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 31;
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
                'article' => 'L. 421-41 à L. 421-54 (section · v 01/01/2026)',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044599003/2026-01-01/',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'NOTICE',
                'reference' => 'ANTS · Taxes sur les cartes grises',
                'url' => 'https://immatriculation.ants.gouv.fr/tout-savoir/taxes-sur-les-cartes-grises',
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
            title: "Taxes liées au certificat d'immatriculation (avant majoration IDF 01/03/2026)",
            pitch: "Période 01/01-28/02/2026 · taxes payées à la délivrance d'une carte grise · taxe régionale Y1 (exonération VE/H₂ facultative par région), taxe formation transport Y2, taxe fixe Y4.",
            body: "Acquittées par le titulaire de la carte grise · le bailleur (société de location) immatricule les véhicules et acquitte ces taxes ponctuelles. Les entreprises utilisatrices ne sont jamais directement redevables. Régime hérité de R-2025-031-bis · l'exonération régionale Y1 pour VE/H₂ reste facultative par région depuis LF 2025 art. 30. L'application ne calcule pas ces taxes · documentées pour exhaustivité.",
        );
    }
}
