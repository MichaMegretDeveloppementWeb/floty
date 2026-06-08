<?php

declare(strict_types=1);

namespace Tests\Feature\User\Settings;

use App\Models\ControlRecipientDelta;
use App\Models\ControlReminderSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature de la page Paramètres > Rappels de contrôles (Chantier B / B1,
 * domaine A). Cycle de rappel par défaut + destinataires universels (niveau 0).
 */
final class ControlReminderSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function edit_renvoie_les_valeurs_par_defaut_a_la_premiere_visite(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/settings/control-reminders')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Settings/ControlReminders/Index')
                ->has('settings', fn (AssertableInertia $s) => $s
                    ->where('daysBefore', 15)
                    ->where('remindOnDueDay', true)
                    ->where('repeatEveryDays', 5)
                    ->where('defaultRecipients', [])
                    ->etc()));

        self::assertSame(1, ControlReminderSettings::query()->count());
    }

    #[Test]
    public function update_persiste_le_cycle_et_les_destinataires_par_defaut(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/settings/control-reminders', [
                'days_before' => 30,
                'remind_on_due_day' => false,
                'repeat_every_days' => 10,
                'default_recipients' => [
                    ['name' => 'Atelier', 'email' => 'Atelier@Exemple.FR'],
                    ['name' => 'Direction', 'email' => 'direction@exemple.fr'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $settings = ControlReminderSettings::singleton();
        self::assertSame(30, $settings->days_before);
        self::assertFalse($settings->remind_on_due_day);
        self::assertSame(10, $settings->repeat_every_days);

        // Deux destinataires niveau 0 persistés en deltas settings/include,
        // emails normalisés (la casse d'entrée est ramenée en minuscules).
        $this->assertDatabaseHas('control_recipient_deltas', [
            'level' => 'settings',
            'operation' => 'include',
            'email' => 'atelier@exemple.fr',
            'name' => 'Atelier',
        ]);
        $this->assertDatabaseHas('control_recipient_deltas', [
            'level' => 'settings',
            'operation' => 'include',
            'email' => 'direction@exemple.fr',
        ]);
        self::assertSame(2, ControlRecipientDelta::query()->where('level', 'settings')->count());
    }

    #[Test]
    public function update_resync_remplace_les_destinataires_sans_doublon(): void
    {
        $user = User::factory()->create();

        $payload = [
            'days_before' => 15,
            'remind_on_due_day' => true,
            'repeat_every_days' => 5,
            'default_recipients' => [
                ['name' => 'A', 'email' => 'a@exemple.fr'],
                ['name' => 'B', 'email' => 'b@exemple.fr'],
            ],
        ];

        $this->actingAs($user)->post('/app/settings/control-reminders', $payload)->assertRedirect();

        // Deuxième envoi avec une liste réduite : l'ancienne doit être remplacée.
        $payload['default_recipients'] = [['name' => 'C', 'email' => 'c@exemple.fr']];
        $this->actingAs($user)->post('/app/settings/control-reminders', $payload)->assertRedirect();

        self::assertSame(1, ControlRecipientDelta::query()->where('level', 'settings')->count());
        $this->assertDatabaseHas('control_recipient_deltas', ['email' => 'c@exemple.fr']);
        $this->assertDatabaseMissing('control_recipient_deltas', ['email' => 'a@exemple.fr']);
        self::assertSame(1, ControlReminderSettings::query()->count());
    }

    #[Test]
    public function update_dedoublonne_les_destinataires_par_email_normalise(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/settings/control-reminders', [
                'days_before' => 15,
                'remind_on_due_day' => true,
                'repeat_every_days' => 5,
                'default_recipients' => [
                    ['name' => 'Doublon 1', 'email' => 'dup@exemple.fr'],
                    ['name' => 'Doublon 2', 'email' => 'DUP@exemple.fr'],
                ],
            ])
            ->assertRedirect();

        self::assertSame(1, ControlRecipientDelta::query()->where('level', 'settings')->count());
    }

    #[Test]
    public function update_rejette_une_repetition_a_zero(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/settings/control-reminders', [
                'days_before' => 15,
                'remind_on_due_day' => true,
                'repeat_every_days' => 0,
                'default_recipients' => [],
            ])
            ->assertSessionHasErrors(['repeat_every_days']);
    }

    #[Test]
    public function update_rejette_un_destinataire_sans_email_valide(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/settings/control-reminders', [
                'days_before' => 15,
                'remind_on_due_day' => true,
                'repeat_every_days' => 5,
                'default_recipients' => [
                    ['name' => 'Sans email', 'email' => 'pas-un-email'],
                ],
            ])
            ->assertSessionHasErrors(['default_recipients.0.email']);
    }

    #[Test]
    public function utilisateur_non_authentifie_redirige_vers_login(): void
    {
        $this->get('/app/settings/control-reminders')->assertRedirect('/login');
    }
}
