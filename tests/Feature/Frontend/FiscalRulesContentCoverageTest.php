<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use App\Models\FiscalRule;
use Database\Seeders\FiscalRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Garantit que le contenu pédagogique frontend
 * (`resources/js/data/fiscalRulesContent.ts`) reste **strictement
 * aligné** avec les règles seedées en BDD (= les classes PHP source
 * de vérité · ADR-0022 finalisée Phase 13 D5.11).
 *
 * **Pourquoi un test PHP qui lit un fichier TS** · le contenu
 * pédagogique vit côté front en markdown riche (cf. prompt original
 * D5.11 · « contenu pédagogique = couche UI séparée des métadonnées »).
 * Sans ce test, ajouter une nouvelle règle PHP sans ajouter son
 * entrée dans `fiscalRulesContent2024` ferait silencieusement
 * disparaître son titre/pitch/exemple de la page Règles · personne
 * ne le verrait jusqu'à ce qu'un utilisateur cherche cette règle.
 *
 * Le test est **bidirectionnel** ·
 *   - aucun rule_code BDD sans entrée dans `fiscalRulesContent2024`
 *   - aucune entrée orpheline dans `fiscalRulesContent2024` qui ne
 *     correspondrait pas à une règle BDD
 */
final class FiscalRulesContentCoverageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fiscal_rules_content_2024_couvre_exactement_les_24_regles_seedees(): void
    {
        $this->seed(FiscalRulesSeeder::class);

        $seededCodes = FiscalRule::query()
            ->where('fiscal_year', 2024)
            ->orderBy('rule_code')
            ->pluck('rule_code')
            ->all();

        $tsPath = base_path('resources/js/data/fiscalRulesContent.ts');
        self::assertFileExists($tsPath);
        $contents = file_get_contents($tsPath);
        self::assertNotFalse($contents);

        // Repérer la déclaration `export const fiscalRulesContent2024 = { ... };`
        // et n'extraire que les codes dans ce bloc · sinon on attraperait
        // aussi des codes cités dans des commentaires ailleurs.
        $startMarker = 'export const fiscalRulesContent2024';
        $startIdx = strpos($contents, $startMarker);
        self::assertNotFalse($startIdx, 'Le fichier .ts doit exporter `fiscalRulesContent2024`.');
        $tail = substr($contents, $startIdx);

        // Match `'R-2024-XXX':` (entrée objet, suivie d'un deux-points)
        preg_match_all("/'(R-2024-\d{3})'\s*:/", $tail, $matches);
        $contentCodes = array_values(array_unique($matches[1]));
        sort($contentCodes);

        // Bidirectionnel · pas d'absence (BDD → TS), pas d'orphelin (TS → BDD).
        $missingInContent = array_diff($seededCodes, $contentCodes);
        $orphansInContent = array_diff($contentCodes, $seededCodes);

        self::assertSame(
            [],
            array_values($missingInContent),
            'Codes seedés en BDD mais absents de fiscalRulesContent2024 · '
            .'la page Règles affichera un fallback générique pour ces règles. '
            .'Ajouter une entrée pour chacune dans resources/js/data/fiscalRulesContent.ts.',
        );

        self::assertSame(
            [],
            array_values($orphansInContent),
            'Entrées orphelines dans fiscalRulesContent2024 (pas de règle PHP correspondante) · '
            .'soit la classe PHP a été retirée et il faut nettoyer le .ts, '
            .'soit la règle a été renommée et l\'entrée .ts pointe vers un code obsolète.',
        );

        // Sanity · attend les 24 règles 2024 (16 pipeline + 8 documentaires).
        self::assertCount(24, $seededCodes);
        self::assertCount(24, $contentCodes);
    }
}
