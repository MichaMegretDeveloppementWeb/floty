<?php

declare(strict_types=1);

namespace App\Repositories\User\Control;

use App\Contracts\Repositories\User\Control\ControlDefinitionWriteRepositoryInterface;
use App\Data\User\Control\ControlRecipientData;
use App\Enums\Control\RecipientLevel;
use App\Enums\Control\RecipientOperation;
use App\Models\ControlDefinition;
use App\Support\Control\RecipientEmail;

final class ControlDefinitionWriteRepository implements ControlDefinitionWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ControlDefinition
    {
        return ControlDefinition::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(ControlDefinition $definition, array $attributes): ControlDefinition
    {
        $definition->fill($attributes);
        $definition->save();

        return $definition;
    }

    public function softDelete(ControlDefinition $definition): void
    {
        $definition->delete();
    }

    /**
     * @param  array<int, ControlRecipientData>  $ownRecipients
     * @param  array<int, string>  $excludedEmails
     */
    public function syncRecipients(ControlDefinition $definition, array $ownRecipients, array $excludedEmails): void
    {
        $definition->recipientDeltas()->delete();

        $seen = [];

        foreach ($ownRecipients as $recipient) {
            $email = RecipientEmail::normalize($recipient->email);

            if (isset($seen[$email])) {
                continue;
            }

            $seen[$email] = true;

            $definition->recipientDeltas()->create([
                'level' => RecipientLevel::Definition,
                'operation' => RecipientOperation::Include,
                'email' => $email,
                'name' => $recipient->name,
            ]);
        }

        foreach ($excludedEmails as $rawEmail) {
            $email = RecipientEmail::normalize($rawEmail);

            if (isset($seen[$email])) {
                continue;
            }

            $seen[$email] = true;

            $definition->recipientDeltas()->create([
                'level' => RecipientLevel::Definition,
                'operation' => RecipientOperation::Exclude,
                'email' => $email,
                'name' => null,
            ]);
        }
    }
}
