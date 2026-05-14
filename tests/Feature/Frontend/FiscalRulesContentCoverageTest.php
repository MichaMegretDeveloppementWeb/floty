<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use App\Models\FiscalRule;
use Database\Seeders\FiscalRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Garantit qu'**aucune règle seedée** ne se retrouve sans contenu
 * pédagogique (Phase 13 D5.12 · ADR-0022 finalisée v1.2).
 *
 * Historique · ce test couvrait initialement la cohérence entre
 * `fiscal_rules` BDD et `resources/js/data/fiscalRulesContent.ts`
 * (couche TS séparée). Depuis la migration D5.12, le contenu
 * pédagogique vit dans la classe PHP de chaque règle et est projeté
 * dans la colonne `fiscal_rules.pedagogical_content`. Le fichier TS
 * a disparu. Ce test garantit désormais ·
 *   - chaque règle seedée a un `pedagogical_content` non-null
 *   - chaque payload contient les champs structurels requis
 *     (tab, section, title, pitch) · les autres sont optionnels
 *
 * Sans ce test, une régression future (ex. ajout d'une nouvelle
 * règle PHP sans `pedagogicalContent()` correctement renseigné)
 * ferait silencieusement passer une carte vide à l'UI.
 */
final class FiscalRulesContentCoverageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function chaque_regle_seedee_a_un_pedagogical_content_complet(): void
    {
        $this->seed(FiscalRulesSeeder::class);

        $rules = FiscalRule::query()
            ->where('fiscal_year', 2024)
            ->orderBy('rule_code')
            ->get();

        self::assertCount(24, $rules);

        foreach ($rules as $rule) {
            self::assertNotNull(
                $rule->pedagogical_content,
                sprintf('%s · pedagogical_content est null en BDD.', $rule->rule_code),
            );

            $content = $rule->pedagogical_content;

            foreach (['tab', 'section', 'title', 'pitch'] as $key) {
                self::assertArrayHasKey(
                    $key,
                    $content,
                    sprintf('%s · champ %s absent du pedagogical_content.', $rule->rule_code, $key),
                );
                self::assertNotEmpty(
                    $content[$key],
                    sprintf('%s · champ %s vide.', $rule->rule_code, $key),
                );
            }
        }
    }
}
