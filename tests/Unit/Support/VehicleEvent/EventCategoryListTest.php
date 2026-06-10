<?php

declare(strict_types=1);

namespace Tests\Unit\Support\VehicleEvent;

use App\Support\VehicleEvent\EventCategoryList;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests Unit de la composition des natures d'événement (refonte type → nature).
 */
final class EventCategoryListTest extends TestCase
{
    #[Test]
    public function les_defauts_viennent_en_premier_puis_les_customs(): void
    {
        self::assertSame(
            ['Entretien', 'Pneus', 'Révision'],
            EventCategoryList::compose(['Entretien'], ['Pneus', 'Révision']),
        );
    }

    #[Test]
    public function dedup_insensible_a_la_casse_le_premier_gagne(): void
    {
        // Un custom dupliquant un défaut (casse différente) ne le réajoute pas.
        self::assertSame(
            ['Contrôle', 'Entretien'],
            EventCategoryList::compose(['Contrôle', 'Entretien'], ['contrôle', 'ENTRETIEN']),
        );
    }

    #[Test]
    public function trim_et_ignore_les_chaines_vides(): void
    {
        self::assertSame(
            ['Accident', 'Choc'],
            EventCategoryList::compose([], ['  Accident  ', '', '   ', 'Choc']),
        );
    }

    #[Test]
    public function aucun_plafond_les_natures_sont_illimitees(): void
    {
        $result = EventCategoryList::compose(
            ['Contrôle', 'Entretien'],
            ['A', 'B', 'C', 'D', 'E'],
        );

        self::assertSame(['Contrôle', 'Entretien', 'A', 'B', 'C', 'D', 'E'], $result);
    }
}
