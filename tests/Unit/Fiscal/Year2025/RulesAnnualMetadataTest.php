<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2025;

use App\Fiscal\Year2025\Year2025Boot;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sanity exhaustive : chaque règle déclarée dans
 * {@see Year2025Boot::rules()} expose une période d'applicabilité
 * qui couvre 2025 entièrement (`2025-01-01` → `2025-12-31`).
 *
 * Spécificité 2025 vs 2024 : 3 règles ont été scindées par LF 2025
 * art. 28 à effet du 01/03/2025. La présente version regroupe donc
 * les règles par « code logique » et vérifie que l'union des périodes
 * couvre l'année entière sans trou.
 */
final class RulesAnnualMetadataTest extends TestCase
{
    #[Test]
    public function chaque_famille_de_regles_2025_couvre_l_annee_complete_sans_trou(): void
    {
        /** @var Container $container */
        $container = $this->app;

        // Regroupement par code logique : R-2025-004-bis → R-2025-004.
        $families = [];
        foreach ((new Year2025Boot)->rules() as $class) {
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
                '2025-01-01 00:00:00',
                $first->applicabilityStart()->format('Y-m-d H:i:s'),
                "Famille {$logicalCode} : applicabilityStart() de la 1ʳᵉ version doit être 2025-01-01 00:00:00.",
            );

            $lastEnd = $last->applicabilityEnd();
            self::assertNotNull(
                $lastEnd,
                "Famille {$logicalCode} : applicabilityEnd() de la dernière version doit être non-null.",
            );
            self::assertSame(
                '2025-12-31 23:59:59',
                $lastEnd->format('Y-m-d H:i:s'),
                "Famille {$logicalCode} : applicabilityEnd() de la dernière version doit être 2025-12-31 23:59:59.",
            );

            // Contiguïté pour les familles scindées : chaque version
            // doit démarrer immédiatement après la fin de la précédente
            // (1 seconde) afin d'éviter tout trou temporel.
            for ($i = 1; $i < count($rules); $i++) {
                $prevEnd = $rules[$i - 1]->applicabilityEnd();
                self::assertNotNull(
                    $prevEnd,
                    "Famille {$logicalCode} : applicabilityEnd() ne peut pas être null pour une version intermédiaire ({$rules[$i - 1]->ruleCode()}).",
                );
                self::assertSame(
                    $prevEnd->addSecond()->format('Y-m-d H:i:s'),
                    $rules[$i]->applicabilityStart()->format('Y-m-d H:i:s'),
                    "Famille {$logicalCode} : trou temporel entre {$rules[$i - 1]->ruleCode()} (fin) et {$rules[$i]->ruleCode()} (début).",
                );
            }
        }
    }
}
