<?php

declare(strict_types=1);

namespace App\Data\User\Invoice;

use App\Enums\Invoice\RegenerateRedirectTarget;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload du POST regenerate facture (Phase 14.O). Le client passe une
 * cible de redirection explicite plutôt que de s'appuyer sur le header
 * Referer côté backend (cf. {@see RegenerateRedirectTarget}).
 *
 * Le défaut `Show` correspond au cas usuel où l'utilisateur regénère
 * depuis la page détail facture et veut voir la nouvelle facture.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class RegenerateInvoiceRequestData extends Data
{
    public function __construct(
        public RegenerateRedirectTarget $redirectTarget = RegenerateRedirectTarget::Show,
    ) {}
}
