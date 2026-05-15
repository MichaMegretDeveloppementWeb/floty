<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\UnavailabilityDocument;

use App\Actions\Unavailability\UploadUnavailabilityDocumentsAction;
use App\Models\UnavailabilityDocument;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lectures UnavailabilityDocument · interface slim conforme ADR-0013.
 */
interface UnavailabilityDocumentReadRepositoryInterface
{
    public function findById(int $id): ?UnavailabilityDocument;

    /**
     * Liste des documents d'une indispo, triés du plus récent au plus
     * ancien (UX naturelle pour la section Documents).
     *
     * @return Collection<int, UnavailabilityDocument>
     */
    public function listForUnavailability(int $unavailabilityId): Collection;

    /**
     * Compte des documents existants pour une indispo · utilisé par
     * {@see UploadUnavailabilityDocumentsAction} pour vérifier la
     * limite des 5 documents avant insert.
     */
    public function countForUnavailability(int $unavailabilityId): int;
}
