<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Models\ContractDocument;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Vue d'un document PDF joint à un contrat - utilisée par la section
 * « Documents » de la page Show contrat (chantier 04.N).
 *
 * `downloadUrl` est calculé côté serveur via `route()` pour que le
 * front puisse l'utiliser directement sans dépendre de Wayfinder
 * pour cette URL spécifique (et permet à un futur endpoint signé
 * d'évoluer transparemment).
 *
 * `sizeFormatted` est pré-calculé serveur (« 2,3 Mo », « 540 Ko »)
 * pour ne pas dupliquer la logique de formatage côté front.
 */
#[TypeScript]
final class ContractDocumentData extends Data
{
    public function __construct(
        public int $id,
        public int $contractId,
        public string $filename,
        public int $sizeBytes,
        public string $sizeFormatted,
        public string $uploadedAt,
        public string $downloadUrl,
    ) {}

    public static function fromModel(ContractDocument $doc): self
    {
        return new self(
            id: $doc->id,
            contractId: $doc->contract_id,
            filename: $doc->filename,
            sizeBytes: $doc->size_bytes,
            sizeFormatted: self::formatSize($doc->size_bytes),
            uploadedAt: $doc->created_at->toIso8601String(),
            downloadUrl: route('user.contracts.documents.show', [
                'contract' => $doc->contract_id,
                'document' => $doc->id,
            ]),
        );
    }

    /**
     * Conversion bytes → format humain FR : « 540 Ko », « 2,3 Mo ».
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
