<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Catégories d'effets de bord déclenchés par l'édition d'une VFC sur
 * ses voisines de l'historique d'un véhicule.
 *
 * Posée par {@see App\Services\Vehicle\FiscalCharacteristicsImpactComputer}
 * et consommée par {@see App\Actions\Vehicle\UpdateFiscalCharacteristicsAction}
 * pour appliquer la cascade d'ajustements et décider si une
 * confirmation utilisateur est requise (les impacts `Delete` sont
 * destructifs).
 */
enum FiscalCharacteristicsImpactType: string
{
    /**
     * Une autre VFC est entièrement engloutie par les nouvelles bornes
     * et doit être supprimée. Destructif → exige confirmation.
     */
    case Delete = 'delete';

    /**
     * `effective_to` d'une VFC doit être ajusté (raccourci ou prolongé)
     * pour rester cohérent avec les nouvelles bornes voisines.
     */
    case AdjustEffectiveTo = 'adjust_effective_to';

    /**
     * `effective_from` d'une VFC doit être ajusté (avancé ou reculé)
     * pour rester cohérent avec les nouvelles bornes voisines.
     */
    case AdjustEffectiveFrom = 'adjust_effective_from';
}
