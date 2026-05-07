<?php

declare(strict_types=1);

namespace App\Exceptions\Invoice;

use App\Exceptions\BaseAppException;

/**
 * Levée par {@see App\Services\Invoice\InvoicePdfStorage::store} quand un
 * PDF existe déjà à un chemin cible. Cohérent avec la doctrine
 * immuabilité (ADR-0008) : on refuse l'écrasement silencieux.
 *
 * Cas typique : retry concurrent ou path collision (ex. après un
 * `cancel + generate` séquentiel mal orchestré). En pratique, le
 * pipeline `RegenerateInvoiceAction` (T4 / Phase 14.P) gère
 * l'orchestration via une transaction + restore on failure ; cette
 * exception est un garde-fou structurel.
 */
final class InvoicePdfAlreadyExistsException extends BaseAppException
{
    public static function forPath(string $path): self
    {
        return new self(
            technicalMessage: "Refusing to overwrite existing invoice PDF at {$path}.",
            userMessage: 'Ce PDF de facture existe déjà sur le serveur. Les factures émises sont immuables (ADR-0008).',
        );
    }
}
