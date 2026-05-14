<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\FiscalRule;

use App\Models\FiscalRule;
use Illuminate\Support\Collection;

/**
 * Lectures sur le domaine FiscalRule.
 *
 * Bien que la requête principale soit triviale (cf. R3-bis), elle est
 * encapsulée ici par cohérence avec les autres domaines : un service
 * de lecture n'instancie jamais une requête Eloquent directement.
 */
interface FiscalRuleReadRepositoryInterface
{
    /**
     * Liste de toutes les règles fiscales d'une année donnée, triées par
     * `display_order`.
     *
     * @return Collection<int, FiscalRule>
     */
    public function findAllForYear(int $year): Collection;

    /**
     * Compte les règles fiscales actives pour une année donnée.
     */
    public function countActiveForYear(int $year): int;

    /**
     * Sous-ensemble des règles d'une année filtré par codes (utilisé
     * par l'aggregator pour exposer les règles ayant participé au
     * calcul d'un véhicule dans le payload de la page Show).
     *
     * @param  list<string>  $codes
     * @return Collection<int, FiscalRule>
     */
    public function findByCodesForYear(int $year, array $codes): Collection;

    /**
     * Map `rule_code => id` pour une année (Phase 13 D5.13 · ADR-0022
     * v1.3). Utilisé par `FiscalRuleQueryService::listForYear()` qui
     * construit ses DTOs depuis les classes PHP et n'a besoin de la
     * BDD que pour récupérer l'id stable de l'index.
     *
     * @return array<string, int>
     */
    public function findIdsByCodeForYear(int $year): array;
}
