<?php

declare(strict_types=1);

namespace App\Services\Contract;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Encapsule l'accès au filesystem pour les documents contrat. Toute la
 * logique d'I/O fichier passe par ici - les Actions ne touchent jamais
 * directement {@see Storage}.
 *
 * Le disque utilisé est celui configuré par défaut (`config('filesystems.default')`).
 * V1 = `local` (private, sous `storage/app/private/`). Bascule vers S3 =
 * juste changer `FILESYSTEM_DISK` dans `.env`, aucun code à modifier.
 *
 * Path layout : `contract-documents/{contract_id}/{uuid}.pdf`
 *   - `contract_id` segment → cleanup facile par contrat si besoin
 *   - UUID dans le filename → évite collisions, ne fuite pas le nom
 *     original (qui peut contenir des infos sensibles)
 *
 * Le nom original (`UploadedFile::getClientOriginalName()`) est
 * persisté en DB (`contract_documents.filename`) pour ré-affichage.
 */
final readonly class ContractDocumentStorage
{
    /**
     * Stocke physiquement un fichier uploadé et retourne les méta-données
     * à persister en DB par l'Action appelante.
     *
     * @return array{storage_path: string, sha256: string, size_bytes: int, mime_type: string, filename: string}
     */
    public function store(UploadedFile $file, int $contractId): array
    {
        $uuid = (string) Str::uuid();
        $path = "contract-documents/{$contractId}/{$uuid}.pdf";

        Storage::disk($this->disk())->putFileAs(
            "contract-documents/{$contractId}",
            $file,
            "{$uuid}.pdf",
        );

        return [
            'storage_path' => $path,
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'size_bytes' => $file->getSize(),
            'mime_type' => $file->getMimeType() ?? 'application/pdf',
            'filename' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Supprime un fichier physique. Idempotent : si le fichier n'existe
     * plus (déjà supprimé ou jamais écrit), pas d'erreur - on assume
     * que l'invariant DB↔disque peut être réparé.
     */
    public function delete(string $storagePath): void
    {
        Storage::disk($this->disk())->delete($storagePath);
    }

    /**
     * Réponse streaming pour download : envoie le fichier au navigateur
     * avec le filename original (Content-Disposition attachment).
     */
    public function streamResponse(string $storagePath, string $originalFilename): StreamedResponse
    {
        return Storage::disk($this->disk())->download($storagePath, $originalFilename);
    }

    private function disk(): string
    {
        /** @var string $disk */
        $disk = config('filesystems.default');

        return $disk;
    }
}
