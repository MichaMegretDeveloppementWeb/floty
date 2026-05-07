<?php

declare(strict_types=1);

namespace App\Services\Fiscal\RiskDetection;

use App\Models\Contract;

/**
 * Calcule un fingerprint déterministe pour un cluster de risque
 * (Phase 11 D2, ADR-0015 § D5).
 *
 * Le fingerprint identifie fonctionnellement un cluster
 * indépendamment de l'ordre dans lequel ses contrats sont passés.
 * Toute modification des `(id, start_date, end_date, vehicle_id)`
 * d'un contrat membre du cluster (ou ajout/retrait d'un membre)
 * change le fingerprint et déclenche une re-revue à la régénération
 * (cf. § 6.5).
 *
 * Stateless. Aucune dépendance, aucune IO. Pure function.
 */
final class FingerprintService
{
    /**
     * Hash SHA-256 hex (64 caractères) du cluster, indépendant de
     * l'ordre des contrats dans la liste.
     *
     * Format des items hashés (tri par `id` ASC) :
     *   `[[id, start_date_iso, end_date_iso, vehicle_id], ...]`
     *
     * @param  iterable<int, Contract>  $contracts
     */
    public function compute(iterable $contracts): string
    {
        $items = [];
        foreach ($contracts as $contract) {
            $items[] = [
                'id' => $contract->id,
                'start_date' => $contract->start_date->toDateString(),
                'end_date' => $contract->end_date->toDateString(),
                'vehicle_id' => $contract->vehicle_id,
            ];
        }

        usort($items, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return hash('sha256', (string) json_encode($items, JSON_THROW_ON_ERROR));
    }
}
