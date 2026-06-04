<?php

declare(strict_types=1);

namespace App\Support\Control;

/**
 * Reminder-recipient email normalisation (Chantier B). A recipient's identity
 * is its normalised email, so include/exclude deltas across levels match
 * regardless of casing or surrounding whitespace. Centralised here so storage
 * (B1) and cascade resolution (B3) share the exact same rule.
 */
final class RecipientEmail
{
    /**
     * Lowercase + trim an email to its canonical identity form.
     */
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
