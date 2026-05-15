<?php

declare(strict_types=1);

namespace App\Services\Unavailability;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Encapsule l'accès au filesystem pour les documents indisponibilité.
 * Toute la logique d'I/O fichier passe par ici · les Actions ne
 * touchent jamais directement {@see Storage}.
 *
 * Le disque utilisé est celui configuré par défaut
 * (`config('filesystems.default')`). V1 = `local` (private). Bascule
 * vers S3 = juste changer `FILESYSTEM_DISK` dans `.env`, aucun code
 * à modifier.
 *
 * Path layout · `unavailability-documents/{unavailability_id}/{uuid}.{ext}`
 *   - `unavailability_id` segment → cleanup facile par indispo
 *   - UUID dans le filename → évite collisions, ne fuite pas le nom
 *     original (qui peut contenir des infos sensibles)
 *   - extension préservée depuis le fichier source · permet au browser
 *     de proposer le bon viewer à l'aperçu (jpg vs pdf vs png)
 *
 * Le nom original (`UploadedFile::getClientOriginalName()`) est
 * persisté en DB (`unavailability_documents.filename`) pour
 * ré-affichage.
 */
final readonly class UnavailabilityDocumentStorage
{
    /**
     * Stocke physiquement un fichier uploadé et retourne les méta-données
     * à persister en DB par l'Action appelante.
     *
     * @return array{storage_path: string, sha256: string, size_bytes: int, mime_type: string, filename: string}
     */
    public function store(UploadedFile $file, int $unavailabilityId): array
    {
        $uuid = (string) Str::uuid();
        $extension = $this->safeExtension($file);
        $path = "unavailability-documents/{$unavailabilityId}/{$uuid}.{$extension}";

        Storage::disk($this->disk())->putFileAs(
            "unavailability-documents/{$unavailabilityId}",
            $file,
            "{$uuid}.{$extension}",
        );

        return [
            'storage_path' => $path,
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'size_bytes' => $file->getSize(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'filename' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Supprime un fichier physique. Idempotent · si le fichier n'existe
     * plus (déjà supprimé ou jamais écrit), pas d'erreur · on assume
     * que l'invariant DB↔disque peut être réparé.
     */
    public function delete(string $storagePath): void
    {
        Storage::disk($this->disk())->delete($storagePath);
    }

    /**
     * Variante best-effort de {@see delete()} · avale toute exception
     * driver (S3 down, permission, I/O) et logge un warning. Utilisée
     * dans deux scénarios de compensation ·
     *   1. Rollback fichier après échec persistance DB lors d'un upload
     *      → on ne veut pas masquer l'exception DB d'origine.
     *   2. Cleanup physique après suppression DB d'un document
     *      → un orphelin disque est préférable à un orphelin record.
     */
    public function safeDelete(string $storagePath): void
    {
        try {
            Storage::disk($this->disk())->delete($storagePath);
        } catch (Throwable $e) {
            Log::warning('UnavailabilityDocumentStorage::safeDelete failed', [
                'storage_path' => $storagePath,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Réponse streaming pour download · envoie le fichier au navigateur
     * avec le filename original (Content-Disposition attachment).
     */
    public function streamResponse(string $storagePath, string $originalFilename): StreamedResponse
    {
        return Storage::disk($this->disk())->download($storagePath, $originalFilename);
    }

    /**
     * Extension sûre dérivée du fichier · `getClientOriginalExtension`
     * vient de l'upload, peut être falsifiée · on whitelist sur les
     * mimes autorisés et on fallback sur l'extension réelle.
     */
    private function safeExtension(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

        if (in_array($ext, $allowed, true)) {
            return $ext;
        }

        // Fallback sur le mime → extension. Validation MIME assurée
        // par le FormRequest en amont, on n'arrive ici qu'avec un
        // fichier déjà validé · le fallback est défensif.
        return match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    private function disk(): string
    {
        /** @var string $disk */
        $disk = config('filesystems.default');

        return $disk;
    }
}
