<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Compteurs des tâches opérationnelles en attente (chantier η Phase 4).
 *
 * Pour le MVP les deux compteurs sont des **placeholders à 0** —
 * chacun sera alimenté par une vraie query quand les features
 * correspondantes seront livrées :
 *   - `pendingDeclarations` : alimenté par chantier δ (workflow
 *     déclaration ADR-0015).
 *   - `pendingInvoices` : alimenté par V1.2 (module facturation).
 *
 * Le rendu UI affiche déjà le compteur `0` comme rendu réaliste pour
 * que l'utilisateur s'habitue à l'emplacement de l'info.
 */
#[TypeScript]
final class DashboardPendingTasksData extends Data
{
    public function __construct(
        public int $pendingDeclarations,
        public int $pendingInvoices,
    ) {}
}
