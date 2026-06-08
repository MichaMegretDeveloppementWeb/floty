<?php

declare(strict_types=1);

namespace Tests\Feature\Control;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ControlDefinition;
use App\Models\ControlRecipientDelta;
use App\Models\ControlReminderLog;
use App\Models\ControlReminderSettings;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleControlOverride;
use App\Notifications\Control\ControlReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature de l'envoi des rappels de contrôle (Chantier B / B3).
 * Référence : aujourd'hui = 2026-06-05 ; un véhicule immatriculé le 2022-06-20
 * a une échéance CT (4 ans) au 2026-06-20, soit un rappel « avant » (15 j) le
 * 2026-06-05.
 */
final class DispatchControlRemindersTest extends TestCase
{
    use RefreshDatabase;

    private const string TODAY = '2026-06-05';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $settings = ControlReminderSettings::singleton();
        $settings->always_notify_email = 'flotte@test.fr';
        $settings->always_notify_name = 'Gestion flotte';
        $settings->save();
    }

    private function vehicleDueSoon(): Vehicle
    {
        return Vehicle::factory()->create(['first_origin_registration_date' => '2022-06-20']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function technicalControl(array $overrides = []): ControlDefinition
    {
        return ControlDefinition::factory()->create(array_merge([
            'name' => 'Contrôle technique',
            'initial_duration_value' => 4,
            'initial_duration_unit' => 'years',
            'cycle_value' => 2,
            'cycle_unit' => 'years',
        ], $overrides));
    }

    private function dispatch(): void
    {
        Artisan::call('controls:dispatch-reminders', ['--date' => self::TODAY]);
    }

    #[Test]
    public function le_rappel_avant_echeance_part_et_est_journalise(): void
    {
        $vehicle = $this->vehicleDueSoon();
        $this->technicalControl();

        $this->dispatch();

        Notification::assertCount(1);
        Notification::assertSentOnDemand(
            ControlReminderNotification::class,
            static fn ($notification, array $channels, object $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'flotte@test.fr',
        );

        $this->assertDatabaseHas('control_reminder_logs', [
            'vehicle_id' => $vehicle->id,
            'due_on' => '2026-06-20',
            'reminder_on' => self::TODAY,
            'kind' => 'before',
            'recipients_count' => 1,
        ]);
    }

    #[Test]
    public function un_second_passage_le_meme_jour_n_envoie_rien(): void
    {
        $this->vehicleDueSoon();
        $this->technicalControl();

        $this->dispatch();
        $this->dispatch();

        Notification::assertCount(1);
        self::assertSame(1, ControlReminderLog::query()->count());
    }

    #[Test]
    public function le_rappel_avant_se_rattrape_quand_le_cron_a_manque_un_jour(): void
    {
        // Échéance 2026-06-20, rappel « avant » canonique le 2026-06-05. Le cron
        // ne tourne pas le 05 mais le 06 : le rappel doit partir quand même,
        // journalisé sur la date canonique (05), pas la date de run (06).
        $vehicle = $this->vehicleDueSoon();
        $this->technicalControl();

        Artisan::call('controls:dispatch-reminders', ['--date' => '2026-06-06']);

        Notification::assertCount(1);
        $this->assertDatabaseHas('control_reminder_logs', [
            'vehicle_id' => $vehicle->id,
            'due_on' => '2026-06-20',
            'reminder_on' => '2026-06-05', // canonique, pas la date de run (06-06)
            'kind' => 'before',
        ]);

        // Un passage le lendemain ne renvoie pas le même « avant » (idempotence
        // par occurrence canonique, malgré une date de run différente).
        Artisan::call('controls:dispatch-reminders', ['--date' => '2026-06-07']);

        Notification::assertCount(1);
        self::assertSame(1, ControlReminderLog::query()->count());
    }

    #[Test]
    public function un_controle_en_pause_est_ignore(): void
    {
        $vehicle = $this->vehicleDueSoon();
        $definition = $this->technicalControl();
        VehicleControlOverride::factory()->overrideOf($definition)->paused()->create([
            'vehicle_id' => $vehicle->id,
        ]);

        $this->dispatch();

        Notification::assertNothingSent();
    }

    #[Test]
    public function un_controle_desactive_est_ignore(): void
    {
        $vehicle = $this->vehicleDueSoon();
        $definition = $this->technicalControl();
        VehicleControlOverride::factory()->overrideOf($definition)->disabled()->create([
            'vehicle_id' => $vehicle->id,
        ]);

        $this->dispatch();

        Notification::assertNothingSent();
    }

    #[Test]
    public function un_vehicule_sorti_de_flotte_n_est_pas_scanne(): void
    {
        Vehicle::factory()->create([
            'first_origin_registration_date' => '2022-06-20',
            'exit_date' => '2025-01-01',
            'exit_reason' => 'sold',
        ]);
        $this->technicalControl();

        $this->dispatch();

        Notification::assertNothingSent();
    }

    #[Test]
    public function le_conducteur_est_ajoute_quand_prevenir_le_conducteur(): void
    {
        $vehicle = $this->vehicleDueSoon();
        $this->technicalControl(['notify_driver' => true]);

        $driver = Driver::factory()->create(['email' => 'conducteur@test.fr']);
        Contract::factory()
            ->forVehicle($vehicle)
            ->forCompany(Company::factory()->create())
            ->withDrivers([$driver])
            ->create(['start_date' => '2026-06-01', 'end_date' => '2026-06-30']);

        $this->dispatch();

        // Gestion flotte (toujours prévenu) + le conducteur.
        Notification::assertCount(2);
        Notification::assertSentOnDemand(
            ControlReminderNotification::class,
            static fn ($notification, array $channels, object $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'conducteur@test.fr',
        );

        $this->assertDatabaseHas('control_reminder_logs', [
            'vehicle_id' => $vehicle->id,
            'recipients_count' => 2,
        ]);
    }

    #[Test]
    public function un_controle_global_surcharge_par_vehicule_est_journalise_et_idempotent(): void
    {
        // Régression : un contrôle GLOBAL surchargé par un override véhicule
        // (ex. un destinataire par défaut retiré sur ce véhicule) porte à la
        // fois un control_definition_id ET un vehicle_control_override_id. Le
        // journal d'idempotence ne doit écrire QU'UNE seule FK (la définition,
        // cf. target_key « def:{id} ») : écrire les deux viole chk_crl_target,
        // l'écriture du journal échoue, l'occurrence n'est jamais enregistrée
        // et le rappel repart à chaque run (1 mail/run au lieu d'1 par occurrence).
        $vehicle = $this->vehicleDueSoon();
        $definition = $this->technicalControl();

        // Destinataire par défaut niveau 0 (en plus de l'email toujours prévenu).
        ControlRecipientDelta::query()->create([
            'level' => 'settings',
            'operation' => 'include',
            'email' => 'atelier@test.fr',
            'name' => 'Atelier',
        ]);

        // Override véhicule actif qui retire ce destinataire par défaut.
        $override = VehicleControlOverride::factory()->overrideOf($definition)->create([
            'vehicle_id' => $vehicle->id,
        ]);
        ControlRecipientDelta::query()->create([
            'level' => 'vehicle',
            'vehicle_control_override_id' => $override->id,
            'operation' => 'exclude',
            'email' => 'atelier@test.fr',
        ]);

        $this->dispatch();
        $this->dispatch();

        // Un seul envoi (flotte@ ; atelier@ retiré au niveau véhicule) malgré
        // deux runs : la deuxième passe est bien dédupliquée.
        Notification::assertCount(1);
        Notification::assertSentOnDemand(
            ControlReminderNotification::class,
            static fn ($notification, array $channels, object $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'flotte@test.fr',
        );

        // Journalisé une seule fois, contre la définition (override mis à NULL
        // pour respecter chk_crl_target).
        self::assertSame(1, ControlReminderLog::query()->count());
        $this->assertDatabaseHas('control_reminder_logs', [
            'vehicle_id' => $vehicle->id,
            'control_definition_id' => $definition->id,
            'vehicle_control_override_id' => null,
            'target_key' => 'def:'.$definition->id,
            'recipients_count' => 1,
        ]);
    }

    #[Test]
    public function le_catalogue_n_est_lu_qu_une_fois_quel_que_soit_le_nombre_de_vehicules(): void
    {
        Vehicle::factory()->count(3)->create(['first_origin_registration_date' => '2022-06-20']);
        $this->technicalControl();

        DB::enableQueryLog();
        $this->dispatch();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $catalogReads = collect($queries)
            ->filter(static fn (array $query): bool => str_contains((string) $query['query'], 'control_definitions'))
            ->count();

        self::assertSame(
            1,
            $catalogReads,
            'Le catalogue des contrôles doit être lu une seule fois pour tout le run, pas une fois par véhicule.',
        );
    }

    #[Test]
    public function plusieurs_destinataires_un_seul_enregistrement_journal(): void
    {
        $vehicle = $this->vehicleDueSoon();
        $this->technicalControl();

        // Destinataire par défaut niveau 0 en plus de l'email toujours prévenu.
        ControlRecipientDelta::query()->create([
            'level' => 'settings',
            'operation' => 'include',
            'email' => 'atelier@test.fr',
            'name' => 'Atelier',
        ]);

        $this->dispatch();

        Notification::assertCount(2);
        self::assertSame(1, ControlReminderLog::query()->count());
        $this->assertDatabaseHas('control_reminder_logs', [
            'vehicle_id' => $vehicle->id,
            'recipients_count' => 2,
        ]);
    }
}
