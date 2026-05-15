<?php

declare(strict_types=1);

namespace App\Data\Shared;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Entrée typée d'un toast côté shared props Inertia (Lot 5 D6 ·
 * F-19-007 + bug back-button).
 *
 * Chaque toast porte un `id` unique (UUID v4) qui sert au front à
 * dédupliquer · sans cet ID, un retour navigateur vers une page déjà
 * visitée déclenche une nouvelle apparition du toast (Inertia restaure
 * la prop `flash` depuis son cache history.state, ce qui re-déclenche
 * le watcher `useFlashToasts`).
 *
 * @property string $id Identifiant unique (UUID v4 ou hash déterministe)
 * @property string $tone success | error | warning | info
 * @property string $message Contenu utilisateur (français)
 */
#[TypeScript]
final class ToastEntryData extends Data
{
    public function __construct(
        public string $id,
        public string $tone,
        public string $message,
    ) {}
}
