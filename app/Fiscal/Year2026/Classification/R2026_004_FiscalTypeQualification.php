<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Classification;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\ReceptionCategory;
use App\Fiscal\Contracts\ClassificationRule;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use App\Models\VehicleFiscalCharacteristics;

/**
 * R-2026-004 · Qualification du type fiscal (M1 / N1).
 *
 * **Règle pipeline (Classification)** · l'article CIBS L. 421-2 est
 * stable sur toute l'année 2026 (version en vigueur depuis le 01/03/2025
 * par LF 2025 art. 28 et art. 75, sans modification par LF 2026 ni par
 * Ordo 2025-1247). **Une seule version 2026** · contrairement à 2025 qui
 * avait R-2025-004 (01/01-28/02) et R-2025-004-bis (01/03-31/12), 2026
 * n'a qu'une règle annuelle (pas de scission ADR-0022).
 *
 * **Cascade applicative inchangée vs R-2025-004-bis** ·
 * - M1 sans usage spécial · taxable
 * - N1 pick-up ≥ 5 places sans usage skiable · taxable
 * - N1 camionnette à 2 rangs amovible affectée transport de personnes · taxable
 * - Sinon · non taxable (court-circuit)
 *
 * **Complément CIBS L. 421-97** · véhicules en circulation pour les seuls
 * besoins de leur construction/commercialisation/réparation/contrôle
 * technique (régime W garage) sont réputés non affectés à des fins
 * économiques. Hors flotte Floty par construction. Article stable depuis
 * 01/01/2025.
 *
 * **Base légale** · audit Chrome live 15/05/2026 ·
 * - CIBS L. 421-2 · version 01/03/2025 → en vigueur (LF 2025 art. 28).
 * - CIBS L. 421-97 · version 01/01/2025 → en vigueur.
 */
final readonly class R2026_004_FiscalTypeQualification implements ClassificationRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2026-004';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return 'Qualification M1 / N1';
    }

    public function description(): string
    {
        return 'Classification du type fiscal du véhicule sur 2026 · cascade applicative M1/N1 identique à 2025 (R-2025-004-bis). L\'article L. 421-2 est stable depuis le 01/03/2025 (LF 2025 art. 28) · pas de modification par LF 2026 ni par l\'Ordonnance n° 2025-1247. Frontière M1 (VP) vs N1 (VU), cas particuliers N1 ≥ 5 places. Complément CIBS L. 421-97 (régime W garage) inchangé.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Classification;
    }

    public function displayOrder(): int
    {
        return 4;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-2',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844510/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'CIBS',
                'article' => 'L. 421-97',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000046196667/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
        ];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2, TaxType::Pollutants];
    }

    public function classify(PipelineContext $context): PipelineContext
    {
        $fiscal = $context->currentFiscalCharacteristics;
        if ($fiscal === null) {
            return $context;
        }

        $isTaxable = $this->isTaxable($fiscal);
        $reason = $isTaxable ? null : $this->nonTaxableReason($fiscal);

        return $context
            ->withIsFiscallyTaxable($isTaxable)
            ->withFiscallyTaxableReason($reason)
            ->withAppliedRule($this->ruleCode());
    }

    private function isTaxable(VehicleFiscalCharacteristics $fiscal): bool
    {
        return match ($fiscal->reception_category) {
            ReceptionCategory::M1 => $fiscal->m1_special_use === false,
            ReceptionCategory::N1 => (
                $fiscal->body_type === BodyType::Pickup
                && $fiscal->seats_count >= 5
                && $fiscal->n1_ski_lift_use === false
            ) || (
                $fiscal->body_type === BodyType::LightTruck
                && $fiscal->n1_removable_second_row_seat === true
                && $fiscal->n1_passenger_transport === true
            ),
        };
    }

    private function nonTaxableReason(VehicleFiscalCharacteristics $fiscal): string
    {
        return match ($fiscal->reception_category) {
            ReceptionCategory::M1 => 'Véhicule M1 à usage spécial (corbillard, ambulance, véhicule blindé) - hors champ fiscal (CIBS L. 421-2).',
            ReceptionCategory::N1 => $this->n1NonTaxableReason($fiscal),
        };
    }

    private function n1NonTaxableReason(VehicleFiscalCharacteristics $fiscal): string
    {
        if ($fiscal->body_type === BodyType::Pickup) {
            if ($fiscal->n1_ski_lift_use) {
                return 'Pick-up N1 affecté à l\'exploitation de remontées mécaniques - hors champ fiscal (CIBS L. 421-2).';
            }

            return 'Pick-up N1 de moins de 5 places - hors champ fiscal (CIBS L. 421-2).';
        }

        if ($fiscal->body_type === BodyType::LightTruck) {
            $hasSecondRow = $fiscal->n1_removable_second_row_seat;
            $isPassengerTransport = $fiscal->n1_passenger_transport;

            if (! $hasSecondRow && ! $isPassengerTransport) {
                return 'Camionnette N1 sans 2ᵉ rangée amovible et non affectée au transport de personnes - hors champ fiscal (CIBS L. 421-2).';
            }

            if (! $hasSecondRow) {
                return 'Camionnette N1 sans 2ᵉ rangée amovible - hors champ fiscal (CIBS L. 421-2).';
            }

            return 'Camionnette N1 non affectée au transport de personnes - hors champ fiscal (CIBS L. 421-2).';
        }

        return 'Véhicule N1 hors des cas taxables (pick-up ≥ 5 places ou camionnette aménagée transport de personnes) - hors champ fiscal (CIBS L. 421-2).';
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Cadre,
            section: RuleSection::Aiguillage,
            title: 'Étape 1 : le véhicule est-il taxable ?',
            pitch: 'Seuls les véhicules M1 (tourisme) et certains N1 (transport de personnes) sont assujettis aux deux taxes en 2026.',
            body: "L'application qualifie automatiquement chaque véhicule à partir de sa catégorie de réception européenne (M1/N1), de sa carrosserie et du nombre de places. Les vrais utilitaires de transport de marchandises (N1 type fourgon, camionnette à 1 rang) sont hors du champ des taxes. La doctrine est strictement identique à 2025 (L. 421-2 stable depuis le 01/03/2025).",
            example: 'Renault Master N1 fourgon de marchandises → hors taxes. Peugeot Partner N1 « camionnette 2 rangs » → taxable.',
        );
    }
}
