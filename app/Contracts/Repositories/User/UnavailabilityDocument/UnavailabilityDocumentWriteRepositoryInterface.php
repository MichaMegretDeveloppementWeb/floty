<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\UnavailabilityDocument;

use App\Models\UnavailabilityDocument;

/**
 * Écritures UnavailabilityDocument · interface slim conforme ADR-0013.
 *
 * Aucune décision métier ici (validation max 5 docs, hash, upload
 * fichier physique) · c'est le rôle des Actions du domaine.
 */
interface UnavailabilityDocumentWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function create(array $row): UnavailabilityDocument;

    /**
     * Hard-delete (pas de soft-delete sur UnavailabilityDocument en V1).
     */
    public function delete(int $id): void;
}
