<?php

declare(strict_types=1);

namespace App\Data\User\Control;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A named reminder recipient ({@see App\Models\ControlRecipientDelta} of
 * operation `include`). Used for the global default recipients (level 0) and a
 * control definition's own recipients (level 1). The email is the recipient's
 * identity (normalised lowercase on write); the name is mandatory for an
 * include.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class ControlRecipientData extends Data
{
    public function __construct(
        #[Required, StringType, Max(120)]
        public string $name,
        #[Required, Email, Max(180)]
        public string $email,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Le nom du destinataire est obligatoire.',
            'email.required' => "L'adresse email du destinataire est obligatoire.",
            'email.email' => "L'adresse email du destinataire n'est pas valide.",
        ];
    }
}
