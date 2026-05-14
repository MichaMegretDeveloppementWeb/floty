<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\FiscalRule;

/**
 * Lectures sur le domaine FiscalRule (Phase 13 D5.14 · ADR-0022 v1.4).
 *
 * La table `fiscal_rules` ne sert plus qu'à indexer les règles
 * fiscales avec un id stable et leur `code_reference` pointant vers
 * la classe PHP de la règle. Toutes les méthodes de lecture qui
 * exposaient les colonnes miroir (name, description, legal_basis,
 * pedagogical_content, etc.) ont été supprimées · ces données vivent
 * désormais exclusivement dans les classes PHP, lues via le registry.
 */
interface FiscalRuleReadRepositoryInterface
{
    /**
     * Map `rule_code => id` pour une année. C'est l'unique méthode
     * de lecture exposée par ce repository · l'id stable suffit pour
     * relier les classes PHP à l'index BDD (FK potentielles, audit
     * SQL, etc.).
     *
     * @return array<string, int>
     */
    public function findIdsByCodeForYear(int $year): array;
}
