<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use Carbon\CarbonImmutable;

/**
 * R-2025-029 · Malus CO₂ à l'immatriculation · **version 01/01 →
 * 28/02/2025** (paramètres pré-durcissement, identiques à 2024).
 *
 * Règle fiscale hors périmètre de l'application · taxe ponctuelle payée
 * à l'immatriculation par le titulaire du certificat d'immatriculation
 * (= bailleur dans le modèle de flotte partagée). L'application ne
 * calcule pas cette taxe (documentée pour exhaustivité du panorama
 * fiscal véhicules).
 *
 * Paramètres 01/01-28/02/2025 (= reconduction stricte 2024) ·
 * - Seuil 118 g CO₂/km
 * - Plafond 60 000 €
 * - Plafonnement à 50 % du prix d'acquisition
 *
 * La période 01/03-31/12/2025 (paramètres durcis par LF 2025 art. 28)
 * est portée par {@see R2025_029bis_RegistrationCo2Malus}.
 *
 * Marquée inactive · règle fiscale réelle mais hors périmètre de calcul
 * de l'application (grisée dans la page Règles de calcul).
 */
final readonly class R2025_029_RegistrationCo2Malus implements InformativeRule
{
    public function ruleCode(): string
    {
        return 'R-2025-029';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2025, 1, 1, 0, 0, 0);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2025, 2, 28, 23, 59, 59);
    }

    public function name(): string
    {
        return "Malus CO₂ à l'immatriculation (version 01/01 → 28/02/2025)";
    }

    public function description(): string
    {
        return "Taxe ponctuelle acquittée par le titulaire du certificat d'immatriculation lors de la première immatriculation en France d'un véhicule de tourisme. Sur la période 01/01-28/02/2025, paramètres reconduits de 2024 · seuil 118 g CO₂/km, plafond 60 000 €, plafonnement à 50 % du prix d'acquisition. Hors périmètre de l'application · le bailleur (société de location) acquitte le malus, les entreprises utilisatrices ne sont jamais redevables (même si le coût peut être refacturé dans le loyer LLD). La période 01/03-31/12/2025 (durcie par LF 2025 art. 28) est portée par R-2025-029-bis.";
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
                'article' => 'L. 421-58 à L. 421-70-1',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044598969/2025-01-01/',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-20-40',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13927-PGP.html/identifiant=BOI-AIS-MOB-10-20-40-20250604',
                'consulted_at' => '2026-05-14',
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
            title: "Malus CO₂ à l'immatriculation (avant LF 2025 art. 28)",
            pitch: "Période 01/01-28/02/2025 · taxe ponctuelle payée à l'achat d'un véhicule neuf, en fonction de ses émissions de CO₂.",
            body: "Acquittée par le titulaire de la carte grise · le bailleur (société de location) détient et immatricule les véhicules · il est donc redevable du malus à l'achat. Les entreprises utilisatrices, qui prennent en location longue durée, ne sont jamais redevables de cette taxe ponctuelle (même si le coût peut être refacturé dans le loyer LLD). L'application ne calcule pas cette taxe · documentée pour exhaustivité. Paramètres reconduits de 2024 sur cette période.",
            example: "Période 01/01-28/02/2025 · un véhicule neuf à 130 g CO₂/km est soumis au malus (au-dessus du seuil de 118 g). Le bailleur acquitte le malus correspondant lors de l'immatriculation.",
        );
    }
}
