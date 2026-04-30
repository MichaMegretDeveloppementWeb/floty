<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\ContractDocument;

use App\Models\ContractDocument;

/**
 * Écritures ContractDocument — interface slim conforme ADR-0013.
 *
 * Aucune décision métier ici (validation max 5 docs, hash, upload
 * fichier physique) — c'est le rôle des Actions du domaine.
 */
interface ContractDocumentWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function create(array $row): ContractDocument;

    /**
     * Hard-delete (pas de soft-delete sur ContractDocument en V1).
     */
    public function delete(int $id): void;
}
