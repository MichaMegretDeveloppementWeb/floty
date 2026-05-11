<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Enums\FiscalDeclaration\DeclarationLifecycleState;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Représente une déclaration fiscale qui mérite l'attention de
 * l'utilisateur pour une entreprise donnée (Phase 11 D4, enrichi
 * D5.9.D avec état de cycle de vie).
 *
 * `deadline` est calculée selon la doctrine CIBS (déclaration N due au
 * 30 avril N+1). `isOverdue` est dérivé de la comparaison avec la
 * date du jour · un retard est porté par l'utilisateur, pas une faute
 * de Floty (l'app ne bloque rien, elle informe).
 *
 * **Phase 12 D5.9.D** : la sémantique de « pending » s'élargit au-delà
 * de « jamais préparée ». Une déclaration apparaît dans la liste tant
 * qu'elle n'est pas en `GeneratedActive` (= finalisée et non obsolète).
 * Les champs `state`, `currentDeclarationId` et `pendingClustersCount`
 * permettent au composant frontend de rendre une carte adaptative qui
 * propose le bon CTA selon l'état (Préparer / Reprendre / Régénérer /
 * Reprendre la régénération).
 */
#[TypeScript]
final class PendingDeclarationData extends Data
{
    public function __construct(
        public int $fiscalYear,
        /** ISO 8601 (Y-m-d). Date limite de finalisation = 30/04 de N+1. */
        public string $deadline,
        public bool $isOverdue,
        public DeclarationLifecycleState $state,
        /**
         * Identifiant de la déclaration courante de l'année pour bâtir
         * un lien `→ /review` ou `→ /show`. `null` quand l'état est
         * `Untouched` (aucune déclaration n'existe encore).
         */
        public ?int $currentDeclarationId,
        public int $pendingClustersCount,
        /**
         * Phase 13 D5.10.A · contexte d'obsolescence pour l'affichage
         * adaptatif des sous-mentions dans `PendingDeclarationsAlert`.
         *
         * Renseignés uniquement pour les états S6 (GeneratedObsolete
         * Orphan) et S7 (RegenerationInProgress) · permet d'afficher
         * « 3 motifs depuis le 14/03/2025 » au lieu d'une mention
         * d'échéance trompeuse (la déclaration a été produite à temps,
         * c'est une mutation de périmètre qui l'a invalidée depuis).
         *
         * `null` / `0` pour Untouched, Draft, Deferred (les états qui
         * n'ont pas encore produit de déclaration et pour lesquels la
         * deadline reste pertinente).
         */
        public ?string $obsoleteSinceDate,
        public int $obsoleteReasonsCount,
    ) {}
}
