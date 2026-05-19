<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Root class for all Floty business exceptions.
 *
 * Splits a technical message ({@see getMessage()}, English, for logs) from a user-facing
 * message ({@see getUserMessage()}, French, surfaced via flash/toast). Subclasses expose
 * static factory methods rather than a public constructor so both messages are authored
 * together at the call site.
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
     * User-facing message (French, formal, no technical details).
     */
    public function getUserMessage(): string
    {
        return $this->userMessage;
    }
}
