<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Models\Vehicle;

/**
 * Réactive un véhicule précédemment sorti de flotte : reset
 * `exit_date` et `exit_reason` à NULL, et passe `current_status` à
 * `Active`.
 *
 * Action volontairement minimale : pas de transaction (un seul UPDATE),
 * pas de validation cross-table (la réactivation n'a pas de
 * pré-condition métier ; un véhicule réactivé pourra recevoir de
 * nouveaux contrats / indispos sans contrainte).
 *
 * Cf. ADR-0018 § 8.2 (UX modale Réactivation).
 */
final readonly class ReactivateVehicleAction
{
    public function __construct(
        private VehicleWriteRepositoryInterface $writer,
    ) {}

    public function execute(int $vehicleId): Vehicle
    {
        return $this->writer->markAsActive($vehicleId);
    }
}
