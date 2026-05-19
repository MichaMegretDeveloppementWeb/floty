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
 * R-2026-033 - Annual incentive tax (TAI) for the acquisition of
 * low-emission light vehicles, version 01/01 → 28/02/2026 (LF 2025
 * art. 28 transitional regime).
 *
 * Documentation-only rule, OUT OF FLOTY V1 SCOPE.
 *
 * 01/01-28/02/2026: articles L. 421-132-1 to L. 421-132-6 are in
 * their "01/03/2025 → 01/03/2026" version (created by LF 2025
 * art. 28). The application note V of art. 28 (1/306th prorata in
 * 2025) is moot but formally carried by the texts until they are
 * rewritten at 01/03/2026.
 *
 * Full 2026 regime effective all year (by L. 421-132-1):
 *   - Annual rate: 4 000 €/missing vehicle (vs 2 000 € in 2025).
 *   - VFE target quota: 18% (vs 15% in 2025).
 *   - Full civil year: no more 1/306th prorata.
 *
 * Stable L. 421-132-2 legal formula: Amount = Annual rate × Gap to
 * target × Annual renewal rate. Amount is zero if the gap is negative
 * (quota reached).
 *
 * The period 01/03-31/12/2026 is carried by
 * {@see R2026_033bis_FleetGreeningIncentiveTax}.
 *
 * Marked inactive: real fiscal rule but out of Floty V1 scope (hits
 * the user company globally, depends on out-of-scope fleet data).
 */
final readonly class R2026_033_FleetGreeningIncentiveTax implements InformativeRule
{
    public function ruleCode(): string
    {
        return 'R-2026-033';
    }

    public function isActive(): bool
    {
        return false;
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
        return 'TAI - acquisition véhicules légers à faibles émissions (version 01/01 → 28/02/2026)';
    }

    public function description(): string
    {
        return "Taxe annuelle incitative créée par LF 2025 art. 95 (CIBS art. L. 421-132-1 à L. 421-132-6) frappant chaque entreprise affectataire qui n'atteint pas un quota cible d'intégration de véhicules à faibles émissions (VFE) dans sa flotte. **Régime plein 2026 effectif dès le 01/01** · tarif annuel 4 000 €/véhicule manquant (vs 2 000 € en 2025), quota cible 18 % (vs 15 % en 2025), année civile complète (sans prorata 1/306e). Période 01/01-28/02/2026 · les textes L. 421-132-1 à L. 421-132-6 sont dans leur version « 01/03/2025 → 01/03/2026 » (note V de LF 2025 art. 28 sur prorata 2025 caduque mais formellement portée). La période 01/03-31/12/2026 (toilettage rédactionnel L. 421-132-5) est portée par R-2026-033-bis. Hors périmètre V1 de l'application.";
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
                'article' => 'L. 421-132-1 à L. 421-132-6 (v 01/01/2026)',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000051187919/2026-01-01/',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'LOI',
                'reference' => 'LF 2025 art. 95 + 28',
                'url' => 'https://www.legifrance.gouv.fr/jorf/id/JORFTEXT000051168007',
                'consulted_at' => '2026-05-15',
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
            title: 'TAI - acquisition de VFE (régime plein 2026)',
            pitch: 'Période 01/01-28/02/2026 · taxe annuelle de la TAI passe à son régime plein 2026 · tarif 4 000 €/véhicule manquant et quota cible VFE 18 %, sur année civile complète (fin du prorata 1/306e de 2025).',
            body: "Formule légale L. 421-132-2 · Montant = Tarif annuel × Écart à l'objectif cible × Taux annuel de renouvellement. Le montant est nul si l'écart est négatif (quota atteint ou dépassé). En 2026, montée en charge du dispositif · tarif unitaire doublé vs 2025 (2 000 → 4 000 €) et quota cible relevé (15 % → 18 %). Hors périmètre V1 de l'application · cette taxe dépend de données globales de la flotte de l'entreprise utilisatrice (flotte totale, VFE entrants, taux de renouvellement) que l'application ne couvre pas.",
            example: "Entreprise affectataire 2026 · flotte taxable 200 véhicules dont 30 VFE (15 %). Quota cible 2026 = 18 %, soit 36 VFE attendus. Écart = 36 - 30 = 6 véhicules. Taux de renouvellement = 40 entrées / 200 = 20 %. Montant TAI = 4 000 € × 6 × 0,20 = 4 800 €/an. À cet exemple s'ajouterait, sur la période 01/03-31/12, l'application des textes toilettés (R-2026-033-bis · même paramètres).",
        );
    }
}
