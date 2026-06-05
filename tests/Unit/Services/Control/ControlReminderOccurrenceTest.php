<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Control;

use App\Enums\Control\ReminderKind;
use App\Services\Control\ControlReminderOccurrence;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests Unit du calcul d'occurrence des rappels de contrôle (Chantier B / B3).
 */
final class ControlReminderOccurrenceTest extends TestCase
{
    private ControlReminderOccurrence $occurrence;

    private CarbonImmutable $nextDue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->occurrence = new ControlReminderOccurrence;
        $this->nextDue = CarbonImmutable::parse('2026-06-15');
    }

    #[Test]
    public function le_rappel_avant_echeance_se_declenche_a_j_moins_days_before(): void
    {
        $today = CarbonImmutable::parse('2026-05-31'); // 15 jours avant le 15/06.

        self::assertSame(
            ReminderKind::Before,
            $this->occurrence->fires($this->nextDue, 15, true, 5, $today),
        );
    }

    #[Test]
    public function aucun_rappel_un_jour_avant_la_fenetre(): void
    {
        $today = CarbonImmutable::parse('2026-06-01'); // 14 jours avant (pas 15).

        self::assertNull($this->occurrence->fires($this->nextDue, 15, true, 5, $today));
    }

    #[Test]
    public function le_rappel_jour_j_se_declenche_a_l_echeance(): void
    {
        self::assertSame(
            ReminderKind::OnDue,
            $this->occurrence->fires($this->nextDue, 15, true, 5, $this->nextDue),
        );
    }

    #[Test]
    public function pas_de_rappel_jour_j_quand_desactive(): void
    {
        self::assertNull($this->occurrence->fires($this->nextDue, 15, false, 5, $this->nextDue));
    }

    #[Test]
    public function le_rappel_apres_echeance_se_repete_tous_les_n_jours(): void
    {
        $plus5 = CarbonImmutable::parse('2026-06-20');
        $plus10 = CarbonImmutable::parse('2026-06-25');

        self::assertSame(ReminderKind::After, $this->occurrence->fires($this->nextDue, 15, true, 5, $plus5));
        self::assertSame(ReminderKind::After, $this->occurrence->fires($this->nextDue, 15, true, 5, $plus10));
    }

    #[Test]
    public function pas_de_rappel_apres_un_jour_hors_cycle(): void
    {
        $plus4 = CarbonImmutable::parse('2026-06-19'); // 4 jours après, cycle 5.

        self::assertNull($this->occurrence->fires($this->nextDue, 15, true, 5, $plus4));
    }

    #[Test]
    public function aucun_rappel_loin_avant_l_echeance(): void
    {
        $today = CarbonImmutable::parse('2026-01-01');

        self::assertNull($this->occurrence->fires($this->nextDue, 15, true, 5, $today));
    }
}
