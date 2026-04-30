<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Vehicle;

use App\Enums\Vehicle\FiscalCharacteristicsImpactType;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Vehicle\FiscalCharacteristicsImpactComputer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre toute la matrice des scénarios de date validée avec le client
 * pour la modale Historique fiscal :
 *
 *  - décalage avant créant un trou             → AdjustEffectiveTo (extend prev)
 *  - décalage avant chevauchant partiellement  → AdjustEffectiveTo (shrink prev)
 *  - décalage avant chevauchant totalement     → Delete (prev avalée)
 *  - décalage arrière créant un trou           → AdjustEffectiveFrom (extend next)
 *  - décalage arrière chevauchant partiellement→ AdjustEffectiveFrom (shrink next)
 *  - décalage arrière chevauchant totalement   → Delete (next avalée)
 *  - bornes inchangées et historique cohérent  → aucun impact
 *  - cas multi-voisins (3+ versions touchées)
 */
final class FiscalCharacteristicsImpactComputerTest extends TestCase
{
    use RefreshDatabase;

    private FiscalCharacteristicsImpactComputer $computer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->computer = new FiscalCharacteristicsImpactComputer;
    }

    #[Test]
    public function bornes_inchangees_avec_historique_contigu_ne_produisent_aucun_impact(): void
    {
        $previous = VehicleFiscalCharacteristics::factory()->create([
            'effective_from' => '2023-01-01',
            'effective_to' => '2023-12-31',
        ]);

        $impacts = $this->computer->compute(
            [$previous],
            CarbonImmutable::parse('2024-01-01'),
            null,
        );

        self::assertSame([], $impacts);
    }

    #[Test]
    public function decalage_en_avant_creant_un_trou_etend_la_precedente(): void
    {
        // Précédente : 01/01 → 31/12. Editée déplacée à 15/02 (avant
        // : était à 01/01) → trou 01/01-14/02 → étendre la précédente
        // jusqu'au 14/02.
        $previous = VehicleFiscalCharacteristics::factory()->create([
            'effective_from' => '2022-01-01',
            'effective_to' => '2023-12-31',
        ]);

        $impacts = $this->computer->compute(
            [$previous],
            CarbonImmutable::parse('2024-02-15'),
            null,
        );

        self::assertCount(1, $impacts);
        self::assertSame(FiscalCharacteristicsImpactType::AdjustEffectiveTo, $impacts[0]->type);
        self::assertSame($previous->id, $impacts[0]->targetId);
        self::assertSame('2024-02-14', $impacts[0]->newEffectiveTo?->toDateString());
    }

    #[Test]
    public function decalage_en_arriere_chevauche_partiellement_la_precedente(): void
    {
        // Précédente : 01/01/2023 → 31/12/2023. Editée recule à 15/06/2023
        // → chevauchement partiel → raccourcir la précédente au 14/06.
        $previous = VehicleFiscalCharacteristics::factory()->create([
            'effective_from' => '2023-01-01',
            'effective_to' => '2023-12-31',
        ]);

        $impacts = $this->computer->compute(
            [$previous],
            CarbonImmutable::parse('2023-06-15'),
            null,
        );

        self::assertCount(1, $impacts);
        self::assertSame(FiscalCharacteristicsImpactType::AdjustEffectiveTo, $impacts[0]->type);
        self::assertSame($previous->id, $impacts[0]->targetId);
        self::assertSame('2023-06-14', $impacts[0]->newEffectiveTo?->toDateString());
    }

    #[Test]
    public function decalage_en_arriere_chevauche_totalement_la_precedente_la_supprime(): void
    {
        // Précédente : 15/01/2023 → 31/03/2023. Editée recule au 01/01/2023
        // (avant prev.from) → la précédente est entièrement engloutie → DELETE.
        $previous = VehicleFiscalCharacteristics::factory()->create([
            'effective_from' => '2023-01-15',
            'effective_to' => '2023-03-31',
        ]);

        $impacts = $this->computer->compute(
            [$previous],
            CarbonImmutable::parse('2023-01-01'),
            null,
        );

        self::assertCount(1, $impacts);
        self::assertSame(FiscalCharacteristicsImpactType::Delete, $impacts[0]->type);
        self::assertSame($previous->id, $impacts[0]->targetId);
        self::assertTrue($impacts[0]->isDestructive());
    }

    #[Test]
    public function decalage_de_la_borne_droite_chevauche_partiellement_la_suivante(): void
    {
        // Editée bornée [01/01/2023 → 31/03/2023]. Suivante 01/04/2023 → 31/12/2023.
        // L'utilisateur étend la fin de l'éditée à 15/06/2023 → chevauchement
        // partiel sur la suivante → AdjustEffectiveFrom à 16/06/2023.
        $next = VehicleFiscalCharacteristics::factory()->create([
            'effective_from' => '2023-04-01',
            'effective_to' => '2023-12-31',
        ]);

        $impacts = $this->computer->compute(
            [$next],
            CarbonImmutable::parse('2023-01-01'),
            CarbonImmutable::parse('2023-06-15'),
        );

        self::assertCount(1, $impacts);
        self::assertSame(FiscalCharacteristicsImpactType::AdjustEffectiveFrom, $impacts[0]->type);
        self::assertSame($next->id, $impacts[0]->targetId);
        self::assertSame('2023-06-16', $impacts[0]->newEffectiveFrom?->toDateString());
    }

    #[Test]
    public function decalage_de_la_borne_droite_chevauche_totalement_la_suivante_la_supprime(): void
    {
        // Suivante 01/04/2023 → 30/06/2023. Editée étend sa fin à 31/12/2023
        // → la suivante est entièrement engloutie → DELETE.
        $next = VehicleFiscalCharacteristics::factory()->create([
            'effective_from' => '2023-04-01',
            'effective_to' => '2023-06-30',
        ]);

        $impacts = $this->computer->compute(
            [$next],
            CarbonImmutable::parse('2023-01-01'),
            CarbonImmutable::parse('2023-12-31'),
        );

        self::assertCount(1, $impacts);
        self::assertSame(FiscalCharacteristicsImpactType::Delete, $impacts[0]->type);
        self::assertSame($next->id, $impacts[0]->targetId);
    }

    #[Test]
    public function passage_a_courante_qui_engouffre_les_versions_posterieures_les_supprime(): void
    {
        // Editée historique [Jan → Mar 2023] devient courante (newTo = null).
        // Toutes les versions postérieures (Apr 2023, Jul 2023, …) sont
        // englouties par [Jan, +∞] → DELETE en cascade.
        $next1 = VehicleFiscalCharacteristics::factory()->create([
            'effective_from' => '2023-04-01',
            'effective_to' => '2023-06-30',
        ]);
        $next2 = VehicleFiscalCharacteristics::factory()->create([
            'effective_from' => '2023-07-01',
            'effective_to' => null,
        ]);

        $impacts = $this->computer->compute(
            [$next1, $next2],
            CarbonImmutable::parse('2023-01-01'),
            null,
        );

        self::assertCount(2, $impacts);
        foreach ($impacts as $impact) {
            self::assertSame(FiscalCharacteristicsImpactType::Delete, $impact->type);
        }
    }

    #[Test]
    public function multi_voisins_decalage_avant_n_etend_que_la_precedente_immediate(): void
    {
        // Trois prédécesseurs : V1 (2020), V2 (2021), V3 (2022). Editée
        // déplacée à 2024-01-01 (gap massif après V3). Seule V3 (la plus
        // proche) doit être étendue ; V1 et V2 ne doivent PAS être touchés.
        $v1 = VehicleFiscalCharacteristics::factory()->create([
            'effective_from' => '2020-01-01',
            'effective_to' => '2020-12-31',
        ]);
        $v2 = VehicleFiscalCharacteristics::factory()->create([
            'effective_from' => '2021-01-01',
            'effective_to' => '2021-12-31',
        ]);
        $v3 = VehicleFiscalCharacteristics::factory()->create([
            'effective_from' => '2022-01-01',
            'effective_to' => '2022-12-31',
        ]);

        $impacts = $this->computer->compute(
            [$v1, $v2, $v3],
            CarbonImmutable::parse('2024-01-01'),
            null,
        );

        self::assertCount(1, $impacts);
        self::assertSame($v3->id, $impacts[0]->targetId);
        self::assertSame(FiscalCharacteristicsImpactType::AdjustEffectiveTo, $impacts[0]->type);
        self::assertSame('2023-12-31', $impacts[0]->newEffectiveTo?->toDateString());
    }

    #[Test]
    public function decalage_arriere_de_grande_amplitude_supprime_plusieurs_versions_intermediaires(): void
    {
        // V1 [01/2020 → 12/2020], V2 [01/2021 → 12/2021], V3 [01/2022 → 12/2022].
        // Editée recule à 06/2020 → V2 et V3 sont entièrement englouties
        // par [06/2020, ?), V1 chevauche partiellement et est SHRINK à 05/2020.
        $v1 = VehicleFiscalCharacteristics::factory()->create([
            'effective_from' => '2020-01-01',
            'effective_to' => '2020-12-31',
        ]);
        $v2 = VehicleFiscalCharacteristics::factory()->create([
            'effective_from' => '2021-01-01',
            'effective_to' => '2021-12-31',
        ]);
        $v3 = VehicleFiscalCharacteristics::factory()->create([
            'effective_from' => '2022-01-01',
            'effective_to' => '2022-12-31',
        ]);

        $impacts = $this->computer->compute(
            [$v1, $v2, $v3],
            CarbonImmutable::parse('2020-06-15'),
            null,
        );

        self::assertCount(3, $impacts);

        $byTarget = [];
        foreach ($impacts as $impact) {
            $byTarget[$impact->targetId] = $impact;
        }

        self::assertSame(FiscalCharacteristicsImpactType::AdjustEffectiveTo, $byTarget[$v1->id]->type);
        self::assertSame('2020-06-14', $byTarget[$v1->id]->newEffectiveTo?->toDateString());

        self::assertSame(FiscalCharacteristicsImpactType::Delete, $byTarget[$v2->id]->type);
        self::assertSame(FiscalCharacteristicsImpactType::Delete, $byTarget[$v3->id]->type);
    }
}
