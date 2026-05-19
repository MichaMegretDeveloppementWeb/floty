<?php

declare(strict_types=1);

namespace App\Services\Fiscal\RiskDetection;

use App\Models\Contract;

/**
 * Deterministic fingerprint for a risk cluster (ADR-0015 § D5).
 *
 * Identifies the cluster regardless of contract ordering. Any change to
 * a member's `(id, start_date, end_date, vehicle_id)` (or the membership
 * list itself) alters the fingerprint and triggers a re-review on
 * regeneration. Pure function · no IO, no dependencies.
 */
final class FingerprintService
{
    /**
     * SHA-256 hex (64 chars) of the cluster contracts, sorted by id.
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
