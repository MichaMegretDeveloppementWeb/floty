<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2026;

use App\Fiscal\Year2026\Year2026Boot;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sanity exhaustive · chaque règle déclarée dans
 * {@see Year2026Boot::rules()} expose une période d'applicabilité
 * qui couvre 2026 entièrement (`2026-01-01` → `2026-12-31`) avec
 * contiguïté stricte entre versions scindées par ADR-0022.
 *
 * **Spécificité 2026 vs 2025** · 3 paires bis dans le pipeline ·
 *   - R-2026-013 / R-2026-013-bis · catégorisation polluants
 *     (rédactionnel Ordo 2025-1247 art. 7 au 01/09/2026)
 *   - R-2026-014 / R-2026-014-bis · tarif polluants
 *     (matériel LF 2026 art. 58 au 01/03/2026 · +30 %)
 *   - R-2026-018 / R-2026-018-bis · OIG (rédactionnel Ordo 2025-1247
 *     art. 4 au 01/09/2026 · inactives Floty V1)
 *
 * Ce test couvre automatiquement toute nouvelle règle 2026 ajoutée au
 * boot · si une règle est ajoutée sans `applicabilityStart/End` couvrant
 * 2026, ou si une scission introduit un trou, il casse · ce qui est voulu.
 */
final class RulesAnnualMetadataTest extends TestCase
{
    #[Test]
    public function chaque_famille_de_regles_2026_couvre_l_annee_complete_sans_trou(): void
    {
        /** @var Container $container */
        $container = $this->app;

        // Regroupement par code logique · R-2026-013-bis → R-2026-013.
        $families = [];
        foreach ((new Year2026Boot)->rules() as $class) {
            $rule = $container->make($class);
            $logicalCode = preg_replace('/-bis$/', '', $rule->ruleCode());
            $families[$logicalCode][] = $rule;
        }

        foreach ($families as $logicalCode => $rules) {
            usort(
                $rules,
                static fn ($a, $b) => $a->applicabilityStart() <=> $b->applicabilityStart(),
            );

            $first = $rules[0];
            $last = $rules[count($rules) - 1];

            self::assertSame(
                '2026-01-01 00:00:00',
                $first->applicabilityStart()->format('Y-m-d H:i:s'),
                "Famille {$logicalCode} · applicabilityStart() de la 1ʳᵉ version doit être 2026-01-01 00:00:00.",
            );

            $lastEnd = $last->applicabilityEnd();
            self::assertNotNull(
                $lastEnd,
                "Famille {$logicalCode} · applicabilityEnd() de la dernière version doit être non-null pour une règle annuelle 2026.",
            );
            self::assertSame(
                '2026-12-31 23:59:59',
                $lastEnd->format('Y-m-d H:i:s'),
                "Famille {$logicalCode} · applicabilityEnd() de la dernière version doit être 2026-12-31 23:59:59.",
            );

            // Contiguïté pour les familles scindées · chaque version
            // doit démarrer immédiatement après la fin de la précédente
            // (1 seconde) afin d'éviter tout trou temporel.
            for ($i = 1; $i < count($rules); $i++) {
                $prevEnd = $rules[$i - 1]->applicabilityEnd();
                self::assertNotNull(
                    $prevEnd,
                    "Famille {$logicalCode} · applicabilityEnd() ne peut pas être null pour une version intermédiaire ({$rules[$i - 1]->ruleCode()}).",
                );
                self::assertSame(
                    $prevEnd->addSecond()->format('Y-m-d H:i:s'),
                    $rules[$i]->applicabilityStart()->format('Y-m-d H:i:s'),
                    "Famille {$logicalCode} · trou temporel entre {$rules[$i - 1]->ruleCode()} (fin) et {$rules[$i]->ruleCode()} (début).",
                );
            }
        }
    }

    #[Test]
    public function pipeline_2026_compte_exactement_21_classes_dont_3_paires_bis(): void
    {
        $rules = (new Year2026Boot)->rules();

        self::assertCount(21, $rules, '21 classes pipeline 2026 attendues (16 actives + 5 inactives).');

        /** @var Container $container */
        $container = $this->app;
        $bisCount = 0;
        foreach ($rules as $class) {
            $rule = $container->make($class);
            if (str_ends_with($rule->ruleCode(), '-bis')) {
                $bisCount++;
            }
        }

        self::assertSame(3, $bisCount, '3 classes -bis attendues · R-2026-013-bis, R-2026-014-bis, R-2026-018-bis.');
    }

    #[Test]
    public function codes_de_regles_pipeline_2026_sont_uniques(): void
    {
        /** @var Container $container */
        $container = $this->app;

        $codes = [];
        foreach ((new Year2026Boot)->rules() as $class) {
            $codes[] = $container->make($class)->ruleCode();
        }

        self::assertSame(count($codes), count(array_unique($codes)), 'Doublons détectés dans les codes pipeline 2026 : '.implode(', ', $codes));
    }

    /**
     * Z9 · audit URLs exhaustif 15/05/2026 · 66/66 URLs vérifiées
     * (bulk curl avec détection « Document non trouvé » + spot-check
     * Chrome live sur 3 URLs critiques · L. 421-120 WLTP, L. 421-121
     * NEDC, L. 421-122 PA). Toutes les URLs Légifrance pointent vers
     * du contenu valide au 15/05/2026 · les barèmes 2026 affichés
     * (Jusqu'à 4 / 5-45 / 46-53 / 54-85 / 86-105 / 106-125 / 126-145 /
     * 146-165 / 166+ pour WLTP) correspondent exactement aux
     * `BracketRange` codés dans R-2026-010/011/012.
     *
     * Ce test garde-fou structurel garantit que chaque règle pipeline
     * 2026 expose au moins une source légale avec URL + consulted_at,
     * empêchant qu'une future modification retire silencieusement la
     * traçabilité légale.
     */
    #[Test]
    public function chaque_regle_pipeline_2026_expose_legal_basis_enrichi(): void
    {
        /** @var Container $container */
        $container = $this->app;

        foreach ((new Year2026Boot)->rules() as $class) {
            $rule = $container->make($class);
            $basis = $rule->legalBasis();

            self::assertNotEmpty($basis, "{$rule->ruleCode()} · legalBasis ne peut pas être vide.");

            foreach ($basis as $entry) {
                self::assertArrayHasKey('url', $entry, "{$rule->ruleCode()} · entrée legalBasis sans clé 'url'.");
                self::assertNotEmpty($entry['url'], "{$rule->ruleCode()} · URL legalBasis vide.");
                self::assertMatchesRegularExpression(
                    '#^https?://#',
                    $entry['url'],
                    "{$rule->ruleCode()} · URL legalBasis invalide (manque schéma http/https) · {$entry['url']}.",
                );
                self::assertArrayHasKey('consulted_at', $entry, "{$rule->ruleCode()} · entrée legalBasis sans clé 'consulted_at'.");
                self::assertMatchesRegularExpression(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $entry['consulted_at'],
                    "{$rule->ruleCode()} · consulted_at au mauvais format · {$entry['consulted_at']}.",
                );
            }
        }
    }
}
