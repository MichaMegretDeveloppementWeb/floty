<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Control;

use App\Data\User\Control\ControlRecipientData;
use App\Models\ControlDefinition;

/**
 * Writes of the global control definitions catalog (Chantier B / B1).
 */
interface ControlDefinitionWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ControlDefinition;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(ControlDefinition $definition, array $attributes): ControlDefinition;

    /**
     * Soft-deletes the definition (recipient deltas stay inert; FK RESTRICT).
     */
    public function softDelete(ControlDefinition $definition): void;

    /**
     * Replaces the definition's level-1 recipient deltas: `include` rows from
     * `$ownRecipients`, `exclude` rows from `$excludedEmails` (inherited
     * defaults removed). Emails are normalised to their identity form.
     *
     * @param  array<int, ControlRecipientData>  $ownRecipients
     * @param  array<int, string>  $excludedEmails
     */
    public function syncRecipients(ControlDefinition $definition, array $ownRecipients, array $excludedEmails): void;
}
