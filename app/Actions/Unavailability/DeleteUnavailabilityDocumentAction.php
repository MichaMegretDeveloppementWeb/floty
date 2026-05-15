<?php

declare(strict_types=1);

namespace App\Actions\Unavailability;

use App\Contracts\Repositories\User\UnavailabilityDocument\UnavailabilityDocumentWriteRepositoryInterface;
use App\Models\UnavailabilityDocument;
use App\Services\Unavailability\UnavailabilityDocumentStorage;

/**
 * Suppression d'un document indisponibilité · hard-delete (DB row +
 * fichier physique).
 *
 * Ordre · DB d'abord, fichier physique ensuite via `safeDelete`. Si la
 * suppression DB échoue, on remonte l'erreur sans toucher au disque
 * (le record reste, le fichier aussi → état cohérent). Si le delete DB
 * réussit mais que le delete physique échoue (panne disk, permission),
 * on logge un warning et on poursuit · l'orphelin fichier est silencieux
 * et purgeable par un job de cleanup, alors qu'un orphelin record
 * (visible dans l'UI sans fichier derrière) serait une régression UX.
 */
final readonly class DeleteUnavailabilityDocumentAction
{
    public function __construct(
        private UnavailabilityDocumentWriteRepositoryInterface $writer,
        private UnavailabilityDocumentStorage $storage,
    ) {}

    public function execute(UnavailabilityDocument $document): void
    {
        $storagePath = $document->storage_path;

        $this->writer->delete($document->id);

        $this->storage->safeDelete($storagePath);
    }
}
