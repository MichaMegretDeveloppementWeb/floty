<?php

declare(strict_types=1);

namespace App\Data\User\VehicleEvent;

use App\Models\VehicleEventDocument;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Attached document of an unavailability, used by the documents section
 * of the unavailability modal on the vehicle page.
 *
 * `downloadUrl` is built server-side via `route()` so the frontend can
 * use it directly (and a future signed endpoint can change transparently).
 * `sizeFormatted` is pre-rendered to avoid duplicating the formatting on
 * the frontend, and `isImage` lets the UI pick between a thumbnail
 * preview and a generic PDF icon.
 */
#[TypeScript]
final class VehicleEventDocumentData extends Data
{
    public function __construct(
        public int $id,
        public int $vehicleEventId,
        public string $filename,
        public int $sizeBytes,
        public string $sizeFormatted,
        public string $mimeType,
        public bool $isImage,
        public string $uploadedAt,
        public string $downloadUrl,
    ) {}

    public static function fromModel(VehicleEventDocument $doc): self
    {
        return new self(
            id: $doc->id,
            vehicleEventId: $doc->vehicle_event_id,
            filename: $doc->filename,
            sizeBytes: $doc->size_bytes,
            sizeFormatted: self::formatSize($doc->size_bytes),
            mimeType: $doc->mime_type,
            isImage: str_starts_with($doc->mime_type, 'image/'),
            uploadedAt: $doc->created_at->toIso8601String(),
            downloadUrl: route('user.vehicle-events.documents.show', [
                'vehicleEvent' => $doc->vehicle_event_id,
                'document' => $doc->id,
            ]),
        );
    }

    /**
     * Bytes -> human-readable French format ("540 Ko", "2,3 Mo"). Uses
     * decimal thresholds (Ko = 1000 bytes) for consistency with browser
     * download UIs.
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
