<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Fiscal\Pipeline\PipelineContext;

/**
 * Règle transverse : prorata journalier, arrondi final, prise en
 * compte des indisponibilités, etc. Appliquée à la fin du pipeline
 * pour transformer les tarifs annuels pleins en montants finaux dus.
 *
 * **Contrat `daysWindow` (chantier dette VFC)** : toute règle
 * transversale qui itère sur des dates (`expandToDaysInYear`,
 * boucles jour-par-jour…) DOIT respecter `$context->daysWindow` si
 * elle est posée (intersection des dates avec la fenêtre). C'est
 * indispensable pour que le calcul reste correct quand le pipeline
 * est exécuté de façon segmentée par
 * {@see App\Fiscal\Pipeline\VfcSegmentedFiscalExecutor} : chaque
 * sous-exécution voit les contrats/indispos entiers (pour préserver
 * la sémantique des règles per-contract comme R-2024-021 LCD), mais
 * ne doit compter que les jours qui tombent dans le segment courant.
 * Voir R-2024-002 et R-2024-008 pour l'exemple d'application.
 */
interface TransversalRule extends FiscalRule
{
    public function apply(PipelineContext $context): PipelineContext;
}
