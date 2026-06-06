<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\VehicleEvent\VehicleEventSystemKind;
use App\Models\User;
use App\Models\VehicleEvent;
use App\Policies\VehicleEventPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cf. plan-remédiation Vague 1 Lot 1 D7 (F-30-003).
 */
final class VehicleEventPolicyTest extends TestCase
{
    #[Test]
    public function un_evenement_utilisateur_autorise_toutes_les_abilities_v1(): void
    {
        $policy = new VehicleEventPolicy;
        $user = new User;
        $vehicleEvent = new VehicleEvent;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $vehicleEvent));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $vehicleEvent));
        $this->assertTrue($policy->delete($user, $vehicleEvent));
    }

    #[Test]
    public function un_repere_systeme_est_consultable_mais_ni_modifiable_ni_supprimable(): void
    {
        $policy = new VehicleEventPolicy;
        $user = new User;
        $systemEvent = new VehicleEvent(['system_kind' => VehicleEventSystemKind::FleetExit]);

        // Lecture autorisée (la fiche détail reste consultable)...
        $this->assertTrue($policy->view($user, $systemEvent));
        // ...mais édition et suppression refusées (piloté par l'état véhicule).
        $this->assertFalse($policy->update($user, $systemEvent));
        $this->assertFalse($policy->delete($user, $systemEvent));
    }
}
