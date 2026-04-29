<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Distingue les deux flux d'édition des caractéristiques fiscales d'un
 * véhicule depuis la page Edit :
 *
 *   - `Correction` : update en place sur la VFC courante (cas d'erreur
 *                    de saisie initiale). Pas de nouvelle ligne, pas de
 *                    motif/note exposés à l'utilisateur — la version
 *                    existante est simplement écrasée.
 *
 *   - `NewVersion` : crée une nouvelle ligne d'historique (cas d'un
 *                    changement réel sur le véhicule), clôture la
 *                    précédente à `effective_from − 1 jour`, et — si
 *                    la date d'effet est rétroactive — supprime les
 *                    versions postérieures à cette date.
 */
#[TypeScript]
enum FiscalChangeMode: string
{
    case Correction = 'correction';
    case NewVersion = 'new_version';

    public function label(): string
    {
        return match ($this) {
            self::Correction => 'Correction de la version courante',
            self::NewVersion => 'Nouvelle version (changement réel)',
        };
    }
}
