<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Classification;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\ReceptionCategory;
use App\Fiscal\Contracts\ClassificationRule;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Pipeline\FiscalPipeline;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use App\Models\VehicleFiscalCharacteristics;

/**
 * R-2024-004 · fiscal type qualification (M1 / N1 boundary).
 *
 * Legal basis: CIBS art. L. 421-2 + BOFiP `BOI-AIS-MOB-10-30-20-20240710` § 60.
 *
 * Cascade:
 *   - M1 with no special use (hearse, ambulance, armoured) → taxable
 *   - N1 pickup ≥ 5 seats, not strictly ski-lift → taxable
 *   - N1 light truck ≥ 2 rows assigned to passenger transport → taxable
 *   - otherwise → not taxable (sets `isFiscallyTaxable = false` on the
 *     context and the pipeline short-circuits)
 *
 * CIBS L. 421-97 complement: vehicles authorised to circulate solely
 * for their construction, marketing, repair or technical inspection
 * (notably "W garage" registration) are deemed not assigned to
 * economic purposes. These are out of Floty's scope by construction
 * but the rule completes R-2024-004's conceptual framework.
 *
 * In addition to the boolean verdict the rule sets a precise exclusion
 * reason (`isFiscallyTaxableReason`) based on the branch taken. This
 * reason is consumed by {@see FiscalPipeline::buildResult()}
 * to display the exact "out of scope" justification rather than a
 * generic message.
 */
final readonly class R2024_004_FiscalTypeQualification implements ClassificationRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2024-004';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Qualification M1 / N1';
    }

    public function description(): string
    {
        return "Classification du type fiscal du véhicule : frontière M1 (VP) vs N1 (VU), cas particuliers N1 ≥ 5 places. Complément CIBS L. 421-97 · les véhicules en circulation pour les seuls besoins de leur construction, commercialisation, réparation ou contrôle technique (par exemple sous régime W garage) sont réputés ne pas être affectés à des fins économiques. Ces véhicules sont par construction hors flotte de l'application.";
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844510/2024-06-01',
                'consulted_at' => '2026-05-06',
            ],
            // CIBS L. 421-97 complement: vehicles deemed not assigned
            // to economic purposes (W garage, demonstration, marketing,
            // repair, technical inspection).
            [
                'type' => 'CIBS',
                'article' => 'L. 421-97',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000046196667/2024-06-01',
                'consulted_at' => '2026-05-14',
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
            // M1: passenger car, taxable unless special use.
            ReceptionCategory::M1 => $fiscal->m1_special_use === false,
            // N1: pickup ≥ 5 seats not ski-lift, OR light truck with
            // removable 2nd row AND assigned to passenger transport.
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

    /**
     * Resolves the exclusion reason based on the cascade branch that
     * dropped the vehicle. Called only when `isTaxable === false`.
     */
    private function nonTaxableReason(VehicleFiscalCharacteristics $fiscal): string
    {
        return match ($fiscal->reception_category) {
            // M1 out of scope ⇒ necessarily m1_special_use=true.
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

            // Remaining pickup branch: seats_count < 5.
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
            pitch: 'Seuls les véhicules de catégorie M1 (tourisme) et certains N1 (transport de personnes) sont assujettis aux deux taxes.',
            body: "L'application qualifie automatiquement chaque véhicule à partir de sa catégorie de réception européenne (M1/N1), de sa carrosserie et du nombre de places. Les vrais utilitaires de transport de marchandises (N1 type fourgon, camionnette à 1 rang) sont hors du champ des taxes.",
            example: 'Renault Master N1 fourgon de marchandises → hors taxes. Peugeot Partner N1 « camionnette 2 rangs » → taxable.',
        );
    }
}
