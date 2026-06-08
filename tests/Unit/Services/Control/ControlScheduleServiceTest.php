<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Control;

use App\Enums\Control\ControlScheduleStatus;
use App\Enums\Control\DurationUnit;
use App\Services\Control\ControlScheduleService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests Unit du moteur d'échéance des contrôles (Chantier B / B2).
 */
final class ControlScheduleServiceTest extends TestCase
{
    private ControlScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ControlScheduleService;
    }

    #[Test]
    public function jamais_realise_echeance_egale_ancre_plus_duree_initiale(): void
    {
        $next = $this->service->nextDueDate(
            CarbonImmutable::parse('2020-01-01'),
            4,
            DurationUnit::Years,
            2,
            DurationUnit::Years,
            null,
        );

        self::assertSame('2024-01-01', $next->toDateString());
    }

    #[Test]
    public function deja_realise_echeance_egale_derniere_execution_plus_cycle(): void
    {
        $next = $this->service->nextDueDate(
            CarbonImmutable::parse('2020-01-01'),
            4,
            DurationUnit::Years,
            2,
            DurationUnit::Years,
            CarbonImmutable::parse('2024-03-10'),
        );

        self::assertSame('2026-03-10', $next->toDateString());
    }

    #[Test]
    public function gere_les_durees_en_mois(): void
    {
        $next = $this->service->nextDueDate(
            CarbonImmutable::parse('2020-01-15'),
            6,
            DurationUnit::Months,
            18,
            DurationUnit::Months,
            null,
        );

        self::assertSame('2020-07-15', $next->toDateString());
    }

    #[Test]
    public function statut_en_retard_quand_echeance_depassee(): void
    {
        $status = $this->service->deriveStatus(
            CarbonImmutable::parse('2024-01-01'),
            null,
            CarbonImmutable::parse('2024-02-01'),
            15,
            null,
        );

        self::assertSame(ControlScheduleStatus::Overdue, $status);
    }

    #[Test]
    public function statut_echeance_proche_dans_la_fenetre_de_rappel(): void
    {
        $status = $this->service->deriveStatus(
            CarbonImmutable::parse('2024-01-20'),
            null,
            CarbonImmutable::parse('2024-01-10'),
            15,
            null,
        );

        self::assertSame(ControlScheduleStatus::DueSoon, $status);
    }

    #[Test]
    public function statut_a_venir_loin_de_l_echeance(): void
    {
        $status = $this->service->deriveStatus(
            CarbonImmutable::parse('2024-06-01'),
            null,
            CarbonImmutable::parse('2024-01-10'),
            15,
            null,
        );

        self::assertSame(ControlScheduleStatus::Upcoming, $status);
    }

    #[Test]
    public function statut_fait_recemment_quand_execution_recente_et_echeance_lointaine(): void
    {
        $status = $this->service->deriveStatus(
            CarbonImmutable::parse('2026-01-05'),
            CarbonImmutable::parse('2024-01-05'),
            CarbonImmutable::parse('2024-01-20'),
            15,
            null,
        );

        self::assertSame(ControlScheduleStatus::DoneRecently, $status);
    }

    #[Test]
    public function statut_a_faire_aujourd_hui_quand_echeance_est_aujourd_hui(): void
    {
        // Échéance pile aujourd'hui : « À faire aujourd'hui », pas « proche ».
        $status = $this->service->deriveStatus(
            CarbonImmutable::parse('2026-06-08'),
            null,
            CarbonImmutable::parse('2026-06-08'),
            15,
            null,
        );

        self::assertSame(ControlScheduleStatus::DueToday, $status);
    }

    #[Test]
    public function statut_non_applicable_quand_echeance_apres_sortie_de_flotte(): void
    {
        $status = $this->service->deriveStatus(
            CarbonImmutable::parse('2026-01-01'),
            null,
            CarbonImmutable::parse('2024-06-01'),
            15,
            CarbonImmutable::parse('2025-01-01'),
        );

        self::assertSame(ControlScheduleStatus::NotApplicable, $status);
    }

    #[Test]
    public function statut_non_applicable_quand_echeance_le_jour_meme_de_la_sortie(): void
    {
        // Véhicule sorti aujourd'hui, échéance aujourd'hui : le contrôle n'est
        // plus à faire (le jour de sortie appartient au nouveau propriétaire).
        $status = $this->service->deriveStatus(
            CarbonImmutable::parse('2026-06-08'),
            null,
            CarbonImmutable::parse('2026-06-08'),
            15,
            CarbonImmutable::parse('2026-06-08'),
        );

        self::assertSame(ControlScheduleStatus::NotApplicable, $status);
    }

    #[Test]
    public function statut_non_applicable_pour_un_vehicule_deja_sorti_meme_si_echeance_anterieure_a_la_sortie(): void
    {
        // Véhicule sorti il y a un an, échéance encore antérieure (donc en retard
        // PENDANT qu'il était en flotte) : il n'est plus la responsabilité de la
        // flotte -> « Non applicable », pas « en retard ».
        $status = $this->service->deriveStatus(
            CarbonImmutable::parse('2023-01-01'),
            null,
            CarbonImmutable::parse('2026-06-08'),
            15,
            CarbonImmutable::parse('2025-06-08'),
        );

        self::assertSame(ControlScheduleStatus::NotApplicable, $status);
    }

    #[Test]
    public function statut_en_retard_conserve_pour_une_echeance_avant_une_sortie_future(): void
    {
        // Sortie PLANIFIÉE (future), échéance déjà dépassée avant cette sortie :
        // le véhicule est encore en flotte, le contrôle reste à faire.
        $status = $this->service->deriveStatus(
            CarbonImmutable::parse('2026-01-01'),
            null,
            CarbonImmutable::parse('2026-06-08'),
            15,
            CarbonImmutable::parse('2026-12-31'),
        );

        self::assertSame(ControlScheduleStatus::Overdue, $status);
    }
}
