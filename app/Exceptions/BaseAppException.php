<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Classe racine de toute exception métier Floty.
 *
 * Sépare explicitement deux messages :
 *   - {@see getMessage()} : technique (anglais, détails de diagnostic - va dans les logs).
 *   - {@see getUserMessage()} : utilisateur (français, destiné à l'affichage via flash/toast).
 *
 * Les sous-classes exposent des factory methods statiques (`::byId()`,
 * `::vehicleAlreadyAssigned()`…) plutôt qu'un constructeur direct. Cela
 * centralise la rédaction des deux messages et rend le code appelant lisible
 * ({@code throw VehicleNotFoundException::byId($id)}).
 *
 * Référence : implementation-rules/gestion-erreurs.md § « Exceptions
 * personnalisées ».
 */
abstract class BaseAppException extends RuntimeException
{
    public function __construct(
        string $technicalMessage,
        protected readonly string $userMessage,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($technicalMessage, $code, $previous);
    }

    /**
     * Message destiné à l'utilisateur - français, ton formel,
     * aucun détail technique (nom de classe, SQL, stack trace).
     */
    public function getUserMessage(): string
    {
        return $this->userMessage;
    }
}
