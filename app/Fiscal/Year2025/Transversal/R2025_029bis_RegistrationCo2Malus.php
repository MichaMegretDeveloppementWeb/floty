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
 * R-2025-029-bis · Malus CO₂ à l'immatriculation · **version 01/03 →
 * 31/12/2025** (DURCISSEMENT par LF 2025 art. 28).
 *
 * **Règle fiscale hors périmètre de l'application** · pendant de
 * {@see R2025_029_RegistrationCo2Malus} qui couvre 01/01-28/02.
 *
 * **Durcissement matériel à effet du 01/03/2025** (LOI n°2025-127 du
 * 14/02/2025 art. 28) ·
 * - Seuil de déclenchement · 118 → **113 g CO₂/km** (durcissement)
 * - Plafond · 60 000 → **70 000 €** (rehaussement)
 * - **Suppression du plafonnement à 50 % du prix d'acquisition**
 *
 * **Marquée inactive** · règle fiscale réelle mais hors périmètre de
 * calcul de l'application (grisée dans la page Règles de calcul).
 */
final readonly class R2025_029bis_RegistrationCo2Malus implements InformativeRule
{
    public function ruleCode(): string
    {
        return 'R-2025-029-bis';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2025, 3, 1, 0, 0, 0);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2025, 12, 31, 23, 59, 59);
    }

    public function name(): string
    {
        return "Malus CO₂ à l'immatriculation (version 01/03 → 31/12/2025, durci par LF 2025 art. 28)";
    }

    public function description(): string
    {
        return "Taxe ponctuelle acquittée par le titulaire du certificat d'immatriculation lors de la première immatriculation en France d'un véhicule de tourisme. Durcissement matériel des paramètres à effet du 01/03/2025 (LOI n°2025-127 du 14/02/2025 art. 28) · seuil abaissé à 113 g CO₂/km (vs 118), plafond rehaussé à 70 000 € (vs 60 000), suppression du plafonnement à 50 % du prix d'acquisition. Hors périmètre de l'application · le bailleur (société de location) acquitte le malus, les entreprises utilisatrices ne sont jamais redevables.";
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
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044598969/2025-03-01/',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-20-40',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13927-PGP.html/identifiant=BOI-AIS-MOB-10-20-40-20250604',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'LOI',
                'reference' => 'LOI n°2025-127 du 14 février 2025 art. 28 (durcissement malus CO₂ immat. à effet 01/03/2025)',
                'url' => 'https://www.legifrance.gouv.fr/jorf/id/JORFTEXT000051183558',
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
            tab: RuleTab::Cadre,
            section: RuleSection::TaxeConnexe,
            title: "Malus CO₂ à l'immatriculation (durci par LF 2025 art. 28)",
            pitch: 'Période 01/03-31/12/2025 · barème durci · seuil abaissé à 113 g (vs 118), plafond rehaussé à 70 000 € (vs 60 000), suppression du plafonnement à 50 % du prix.',
            body: 'Même mécanique que la période précédente (R-2025-029) avec paramètres durcis · un véhicule à 115 g CO₂/km, hors malus en jan-fév 2025, devient soumis dès le 01/03/2025. Hors périmètre de l\'application · le bailleur paie, l\'entreprise utilisatrice ne paie pas directement (refacturation possible via loyer LLD).',
            example: 'Un véhicule neuf émettant 150 g CO₂/km immatriculé en mars 2025 est soumis au malus (au-dessus du seuil de 113 g). Le bailleur acquitte le malus correspondant.',
        );
    }
}
