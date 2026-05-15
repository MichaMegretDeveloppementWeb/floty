<?php

declare(strict_types=1);

namespace App\Actions\Unavailability;

use App\Contracts\Repositories\User\UnavailabilityDocument\UnavailabilityDocumentReadRepositoryInterface;
use App\Contracts\Repositories\User\UnavailabilityDocument\UnavailabilityDocumentWriteRepositoryInterface;
use App\Exceptions\Unavailability\TooManyUnavailabilityDocumentsException;
use App\Models\Unavailability;
use App\Models\UnavailabilityDocument;
use App\Services\Unavailability\UnavailabilityDocumentStorage;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Upload d'un justificatif (image ou PDF) sur une indisponibilité (P1).
 *
 * Vérifie la limite V1 (5 documents max par indispo) avant de stocker
 * physiquement et de persister.
 *
 * Le filesystem n'est pas transactionnel · on stocke d'abord le fichier
 * puis on persiste la ligne DB. Si la persistance DB échoue, on
 * compense en supprimant le fichier physique pour éviter de laisser un
 * orphelin disque · c'est un best-effort, si la compensation échoue à
 * son tour on relance quand même l'exception d'origine (l'utilisateur
 * voit l'erreur, un orphelin disque reste récupérable par un job de
 * cleanup, contrairement à un orphelin DB qui serait visible dans l'UI).
 */
final readonly class UploadUnavailabilityDocumentAction
{
    public const int MAX_DOCUMENTS_PER_UNAVAILABILITY = 5;

    public function __construct(
        private UnavailabilityDocumentReadRepositoryInterface $reader,
        private UnavailabilityDocumentWriteRepositoryInterface $writer,
        private UnavailabilityDocumentStorage $storage,
    ) {}

    public function execute(Unavailability $unavailability, UploadedFile $file, int $uploadedByUserId): UnavailabilityDocument
    {
        $current = $this->reader->countForUnavailability($unavailability->id);

        if ($current >= self::MAX_DOCUMENTS_PER_UNAVAILABILITY) {
            throw TooManyUnavailabilityDocumentsException::limitReached(
                unavailabilityId: $unavailability->id,
                currentCount: $current,
                maxAllowed: self::MAX_DOCUMENTS_PER_UNAVAILABILITY,
            );
        }

        $meta = $this->storage->store($file, $unavailability->id);

        try {
            return $this->writer->create([
                'unavailability_id' => $unavailability->id,
                'filename' => $meta['filename'],
                'storage_path' => $meta['storage_path'],
                'size_bytes' => $meta['size_bytes'],
                'sha256' => $meta['sha256'],
                'mime_type' => $meta['mime_type'],
                'uploaded_by' => $uploadedByUserId,
            ]);
        } catch (Throwable $e) {
            $this->storage->safeDelete($meta['storage_path']);
            throw $e;
        }
    }
}
