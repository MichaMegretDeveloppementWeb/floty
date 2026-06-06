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
 * Échéance de référence : 2026-06-15 (donc fenêtre « avant » ouverte le 05-31).
 * Le matching est FENÊTRÉ : un jour de cron manqué est rattrapé, et la date
 * canonique retournée (clé d'idempotence) est le 1er jour de la fenêtre.
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
    public function le_rappel_avant_echeance_se_declenche_a_l_ouverture_de_la_fenetre(): void
    {
        $today = CarbonImmutable::parse('2026-05-31'); // 15 jours avant le 15/06.

        $fired = $this->occurrence->fires($this->nextDue, 15, true, 5, $today);

        self::assertNotNull($fired);
        self::assertSame(ReminderKind::Before, $fired->kind);
        self::assertSame('2026-05-31', $fired->scheduledOn->toDateString());
    }

    #[Test]
    public function le_rappel_avant_se_rattrape_apres_un_jour_manque(): void
    {
        // Cron tombé le 05-31 : le 06-01 (14 j avant) rattrape le « avant », et
        // la date canonique reste le 05-31 -> dédup contre l'envoi unique.
        $today = CarbonImmutable::parse('2026-06-01');

        $fired = $this->occurrence->fires($this->nextDue, 15, true, 5, $today);

        self::assertNotNull($fired);
        self::assertSame(ReminderKind::Before, $fired->kind);
        self::assertSame('2026-05-31', $fired->scheduledOn->toDateString());
    }

    #[Test]
    public function aucun_rappel_avant_l_ouverture_de_la_fenetre(): void
    {
        $today = CarbonImmutable::parse('2026-05-30'); // 16 jours avant.

        self::assertNull($this->occurrence->fires($this->nextDue, 15, true, 5, $today));
    }

    #[Test]
    public function le_rappel_jour_j_se_declenche_a_l_echeance(): void
    {
        $fired = $this->occurrence->fires($this->nextDue, 15, true, 5, $this->nextDue);

        self::assertNotNull($fired);
        self::assertSame(ReminderKind::OnDue, $fired->kind);
        self::assertSame('2026-06-15', $fired->scheduledOn->toDateString());
    }

    #[Test]
    public function le_rappel_jour_j_se_rattrape_avant_le_premier_apres(): void
    {
        // Cron tombé le jour J : le 06-19 (J+4, avant le 1er « après » à J+5)
        // rattrape le jour J, date canonique = l'échéance.
        $today = CarbonImmutable::parse('2026-06-19');

        $fired = $this->occurrence->fires($this->nextDue, 15, true, 5, $today);

        self::assertNotNull($fired);
        self::assertSame(ReminderKind::OnDue, $fired->kind);
        self::assertSame('2026-06-15', $fired->scheduledOn->toDateString());
    }

    #[Test]
    public function pas_de_rappel_jour_j_quand_desactive(): void
    {
        self::assertNull($this->occurrence->fires($this->nextDue, 15, false, 5, $this->nextDue));
        // Et pas de rattrapage jour J non plus quand il est désactivé.
        self::assertNull($this->occurrence->fires($this->nextDue, 15, false, 5, CarbonImmutable::parse('2026-06-19')));
    }

    #[Test]
    public function le_rappel_apres_echeance_se_repete_tous_les_n_jours(): void
    {
        $plus5 = CarbonImmutable::parse('2026-06-20');
        $plus10 = CarbonImmutable::parse('2026-06-25');

        $fired5 = $this->occurrence->fires($this->nextDue, 15, true, 5, $plus5);
        $fired10 = $this->occurrence->fires($this->nextDue, 15, true, 5, $plus10);

        self::assertNotNull($fired5);
        self::assertSame(ReminderKind::After, $fired5->kind);
        self::assertSame('2026-06-20', $fired5->scheduledOn->toDateString());

        self::assertNotNull($fired10);
        self::assertSame(ReminderKind::After, $fired10->kind);
        self::assertSame('2026-06-25', $fired10->scheduledOn->toDateString());
    }

    #[Test]
    public function le_rappel_apres_se_rattrape_dans_sa_fenetre(): void
    {
        // Cron tombé le J+5 : le J+6 rattrape, date canonique = J+5 (06-20).
        $plus6 = CarbonImmutable::parse('2026-06-21');

        $fired = $this->occurrence->fires($this->nextDue, 15, true, 5, $plus6);

        self::assertNotNull($fired);
        self::assertSame(ReminderKind::After, $fired->kind);
        self::assertSame('2026-06-20', $fired->scheduledOn->toDateString());
    }

    #[Test]
    public function aucun_rappel_loin_avant_l_echeance(): void
    {
        $today = CarbonImmutable::parse('2026-01-01');

        self::assertNull($this->occurrence->fires($this->nextDue, 15, true, 5, $today));
    }
}
