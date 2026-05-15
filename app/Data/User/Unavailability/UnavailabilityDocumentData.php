<?php

declare(strict_types=1);

namespace App\Data\User\Unavailability;

use App\Models\UnavailabilityDocument;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Vue d'un justificatif joint à une indisponibilité (P1) · utilisée
 * par la section « Documents » du modal indisponibilité côté fiche
 * véhicule.
 *
 * `downloadUrl` est calculé côté serveur via `route()` pour que le
 * front puisse l'utiliser directement sans dépendre de Wayfinder
 * pour cette URL spécifique (et permet à un futur endpoint signé
 * d'évoluer transparemment).
 *
 * `sizeFormatted` est pré-calculé serveur (« 2,3 Mo », « 540 Ko »)
 * pour ne pas dupliquer la logique de formatage côté front.
 *
 * `isImage` permet à l'UI de proposer un aperçu thumbnail vs une
 * icône PDF générique.
 */
#[TypeScript]
final class UnavailabilityDocumentData extends Data
{
    public function __construct(
        public int $id,
        public int $unavailabilityId,
        public string $filename,
        public int $sizeBytes,
        public string $sizeFormatted,
        public string $mimeType,
        public bool $isImage,
        public string $uploadedAt,
        public string $downloadUrl,
    ) {}

    public static function fromModel(UnavailabilityDocument $doc): self
    {
        return new self(
            id: $doc->id,
            unavailabilityId: $doc->unavailability_id,
            filename: $doc->filename,
            sizeBytes: $doc->size_bytes,
            sizeFormatted: self::formatSize($doc->size_bytes),
            mimeType: $doc->mime_type,
            isImage: str_starts_with($doc->mime_type, 'image/'),
            uploadedAt: $doc->created_at->toIso8601String(),
            downloadUrl: route('user.unavailabilities.documents.show', [
                'unavailability' => $doc->unavailability_id,
                'document' => $doc->id,
            ]),
        );
    }

    /**
     * Conversion bytes → format humain FR · « 540 Ko », « 2,3 Mo ».
     * Seuils décimaux (Ko = 1000 octets, pas 1024) pour cohérence
     * avec l'affichage navigateur quand on télécharge.
     */
    private static function formatSize(int $bytes): string
    {
        if ($bytes < 1_000) {
            return "{$bytes} o";
        }

        if ($bytes < 1_000_000) {
            $kb = $bytes / 1_000;

            return number_format($kb, $kb < 10 ? 1 : 0, ',', ' ').' Ko';
        }

        $mb = $bytes / 1_000_000;

        return number_format($mb, 1, ',', ' ').' Mo';
    }
}
