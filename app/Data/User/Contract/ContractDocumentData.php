<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Models\ContractDocument;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * PDF document attached to a contract.
 *
 * `downloadUrl` is resolved server-side so the front-end does not need a
 * dedicated Wayfinder binding. `sizeFormatted` is pre-computed to avoid
 * duplicating formatting logic on the client.
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
     * Format bytes as a French human-readable size (decimal thresholds:
     * Ko = 1000 bytes, matching browser download UI).
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
