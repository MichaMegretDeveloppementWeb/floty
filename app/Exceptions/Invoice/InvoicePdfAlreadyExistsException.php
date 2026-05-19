<?php

declare(strict_types=1);

namespace App\Exceptions\Invoice;

use App\Exceptions\BaseAppException;

/**
 * Invoice PDF already exists at the target path. Refuses silent overwrite (immutability doctrine, ADR-0008).
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
