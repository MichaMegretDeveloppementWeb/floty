<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Option couleur entreprise pour le `<SelectInput>` du formulaire de
 * création / édition. La valeur correspond à un slug de l'enum
 * `CompanyColor`, le label est la traduction française.
 */
#[TypeScript]
final class CompanyColorOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}
}
