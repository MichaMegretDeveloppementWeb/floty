<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Stratégie de comblement du trou laissé par la suppression d'une
 * VFC depuis l'historique. L'utilisateur choisit dans le ConfirmModal :
 *
 *   - `ExtendPrevious` : la VFC qui précédait la supprimée voit son
 *                        `effective_to` repoussé jusqu'à
 *                        l'`effective_to` de la supprimée (ou à null
 *                        si la supprimée était courante).
 *
 *   - `ExtendNext`     : la VFC qui suivait la supprimée voit son
 *                        `effective_from` ramené à l'`effective_from`
 *                        de la supprimée (ou à la date d'acquisition
 *                        du véhicule si la supprimée était la version
 *                        initiale).
 *
 * Le 3ᵉ choix « Annuler » est purement UI (l'utilisateur ferme le
 * modal sans soumettre) — il ne remonte pas au backend.
 */
#[TypeScript]
enum FiscalCharacteristicsExtensionStrategy: string
{
    case ExtendPrevious = 'extend_previous';
    case ExtendNext = 'extend_next';

    public function label(): string
    {
        return match ($this) {
            self::ExtendPrevious => 'Étendre la version précédente',
            self::ExtendNext => 'Étendre la version suivante',
        };
    }
}
