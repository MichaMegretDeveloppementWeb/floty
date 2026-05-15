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
 * R-2026-033-bis · Taxe annuelle incitative TAI · **version 01/03 →
 * 31/12/2026** (toilettage rédactionnel L. 421-132-5).
 *
 * **Règle documentaire-only · HORS PÉRIMÈTRE FLOTY V1.**
 *
 * **Scission ADR-0022 strict · toilettage RÉDACTIONNEL** ·
 * - L'article L. 421-132-5 a une « Version en vigueur du 01/03/2025 au
 *   01/03/2026 » selon audit Chrome live 15/05/2026 · une nouvelle
 *   version le remplace au 01/03/2026.
 * - La modification est probablement rédactionnelle (suppression de la
 *   note transitoire V de LF 2025 art. 28 sur le prorata 1/306e en
 *   2025, devenue caduque).
 * - **Paramètres effectifs 2026 inchangés** entre v 01/01-28/02 et
 *   v 01/03-31/12 · tarif annuel 4 000 €, quota cible VFE 18 %, année
 *   civile complète.
 *
 * **Pendant de** · {@see R2026_033_FleetGreeningIncentiveTax} qui couvre
 * 01/01-28/02/2026.
 *
 * **Audit Chrome live 15/05/2026** · L. 421-132-5 v expirée
 * « 01/03/2025 → 01/03/2026 » confirmée sur URL section
 * LEGISCTA000051187919/2026-01-01. Section LEGISCTA000051187919
 * /2026-03-01 confirme la mise à jour des textes au 01/03/2026.
 *
 * **Marquée inactive** · règle fiscale réelle mais hors périmètre V1
 * de l'application.
 */
final readonly class R2026_033bis_FleetGreeningIncentiveTax implements InformativeRule
{
    public function ruleCode(): string
    {
        return 'R-2026-033-bis';
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
        return CarbonImmutable::create(2026, 3, 1, 0, 0, 0);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2026, 12, 31, 23, 59, 59);
    }

    public function name(): string
    {
        return 'TAI - acquisition véhicules légers à faibles émissions (version 01/03 → 31/12/2026, toilettage L. 421-132-5)';
    }

    public function description(): string
    {
        return "Pendant de R-2026-033 pour la période 01/03-31/12/2026. Toilettage rédactionnel de L. 421-132-5 (« Version en vigueur du 01/03/2025 au 01/03/2026 ») · la note transitoire V de LF 2025 art. 28 sur le prorata 1/306e (applicable uniquement en 2025) est caduque et formellement retirée. Paramètres effectifs 2026 inchangés · tarif annuel 4 000 €/véhicule manquant, quota cible VFE 18 %, année civile complète. Scission ADR-0022 strict obligatoire (une version légale = une classe PHP), pas un changement matériel. Hors périmètre V1 de l'application.";
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
                'article' => 'L. 421-132-1 à L. 421-132-6 (v 01/03/2026)',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000051187919/2026-03-01/',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'LOI',
                'reference' => 'LF 2025 art. 95',
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
        return [TaxType::Co2];
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Connexe,
            section: RuleSection::TaxeConnexe,
            title: 'TAI - acquisition de VFE (textes toilettés 01/03/2026)',
            pitch: 'Période 01/03-31/12/2026 · même régime plein TAI que la période 01/01-28/02 · tarif 4 000 €, quota 18 %. La note transitoire 2025 sur le prorata 1/306e est formellement retirée des textes.',
            body: "Mêmes paramètres effectifs que la période précédente (R-2026-033 v 01/01-28/02/2026) · tarif annuel 4 000 €/véhicule manquant, quota cible VFE 18 %, année civile complète. La scission est imposée par ADR-0022 strict du fait du changement de version de l'article porteur L. 421-132-5 au 01/03/2026 · sans impact pratique sur le calcul. Hors périmètre V1 de l'application.",
            example: "Une entreprise affectataire dont la flotte taxable 2026 atteint 30/36 VFE (manque 6 sur quota 18 %), avec un taux de renouvellement annuel de 20 %, acquitte 4 000 € × 6 × 0,20 = **4 800 €** de TAI au titre de 2026, quelle que soit la période de l'année (R-2026-033 ou R-2026-033-bis · doctrine identique).",
        );
    }
}
