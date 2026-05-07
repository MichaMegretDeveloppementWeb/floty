<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Représente une déclaration fiscale attendue mais pas encore générée
 * pour une entreprise (Phase 11 D4). Sert l'alerte « À finaliser » sur
 * l'onglet Vue d'ensemble de la fiche entreprise.
 *
 * `deadline` est calculée selon la doctrine CIBS (déclaration N due au
 * 30 avril N+1). `isOverdue` est dérivé de la comparaison avec la
 * date du jour : un retard est porté par l'utilisateur, pas une faute
 * de Floty (l'app ne bloque rien, elle informe).
 */
#[TypeScript]
final class PendingDeclarationData extends Data
{
    public function __construct(
        public int $fiscalYear,
        /** ISO 8601 (Y-m-d). Date limite de finalisation = 30/04 de N+1. */
        public string $deadline,
        public bool $isOverdue,
    ) {}
}
