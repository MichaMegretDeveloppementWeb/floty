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
 * R-2026-029 - Registration CO₂ malus, version 01/01 → 31/08/2026
 * (LF 2024 art. 97-20° parameters).
 *
 * Out-of-scope fiscal rule: one-shot tax paid at registration by the
 * holder of the registration certificate (= renter in the shared-fleet
 * model). Floty does not compute this tax.
 *
 * 2026 parameters (programmed hardening, LF 2024 art. 97-20°):
 * - Trigger threshold: 108 g CO₂/km (vs 113 in 2025, -5 g)
 * - Cap: 80 000 € (vs 70 000 in 2025, +10K€)
 * - Removal of 50% cap maintained since LF 2025
 *
 * ADR-0022 split vs R-2026-029-bis: L. 421-62 modified by Ordonnance
 * n° 2025-1247 du 17/12/2025 art. 4 (effective 01/09/2026 by art. 49).
 * The cleanup is purely editorial: 2026 scales are identical between
 * v 01/01-31/08 and v 01/09. The split is mandated by strict ADR-0022
 * (one legal version = one PHP class), not by a material change.
 *
 * Marked inactive: real fiscal rule but out of Floty calculation
 * scope (greyed out in the rules page).
 */
final readonly class R2026_029_RegistrationCo2Malus implements InformativeRule
{
    public function ruleCode(): string
    {
        return 'R-2026-029';
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
        return CarbonImmutable::create(2026, 8, 31, 23, 59, 59);
    }

    public function name(): string
    {
        return "Malus CO₂ à l'immatriculation (version 01/01 → 31/08/2026)";
    }

    public function description(): string
    {
        return "Taxe ponctuelle acquittée par le titulaire du certificat d'immatriculation lors de la première immatriculation en France d'un véhicule de tourisme. Durcissement programmé LF 2024 art. 97-20° en vigueur dès le 01/01/2026 · seuil abaissé à 108 g CO₂/km (vs 113 en 2025), plafond rehaussé à 80 000 € (vs 70 000), suppression du plafonnement 50 % maintenue depuis LF 2025. Hors périmètre de l'application · le bailleur (société de location) acquitte le malus, les entreprises utilisatrices ne sont jamais redevables. La période 01/09-31/12/2026 (toilettage rédactionnel Ordo 2025-1247 art. 4) est portée par R-2026-029-bis · paramètres identiques.";
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
                'article' => 'L. 421-58 à L. 421-70-1 (section)',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044598969/2026-01-01/',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-20-40',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13927-PGP.html/identifiant=BOI-AIS-MOB-10-20-40-20250604',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'LOI',
                'reference' => 'LF 2024 art. 97-20°',
                'url' => 'https://www.legifrance.gouv.fr/jorf/id/JORFTEXT000048727323',
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
            tab: RuleTab::HorsPerimetre,
            section: RuleSection::TaxeConnexe,
            title: "Malus CO₂ à l'immatriculation 2026 (durcissement programmé)",
            pitch: "Période 01/01-31/08/2026 · taxe ponctuelle payée à l'achat d'un véhicule neuf en fonction de ses émissions CO₂. Barème durci au 01/01/2026 par LF 2024 art. 97-20° · seuil 108 g (vs 113 en 2025), plafond 80 000 € (vs 70 000).",
            body: "Acquittée par le titulaire de la carte grise · le bailleur (société de location) détient et immatricule les véhicules · il est donc redevable du malus à l'achat. Les entreprises utilisatrices, qui prennent en location longue durée, ne sont jamais redevables de cette taxe ponctuelle (même si le coût peut être refacturé dans le loyer LLD). L'application ne calcule pas cette taxe · documentée pour exhaustivité.",
            example: "Période 01/01-31/08/2026 · un véhicule neuf à 115 g CO₂/km (WLTP) est désormais soumis au malus dès 2026 (au-dessus du nouveau seuil de 108 g) alors qu'il était sous le seuil 2025 (113 g). Le tarif lu dans L. 421-62 v 2026 = 210 € (115 g). Le bailleur acquitte ce montant lors de l'immatriculation.",
        );
    }
}
