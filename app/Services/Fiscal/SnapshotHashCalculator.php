<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

/**
 * Calcul déterministe d'une empreinte SHA-256 sur le snapshot fiscal d'une
 * déclaration (Phase 13 D5.10.J).
 *
 * **Sémantique** · empreinte du contenu fiscal (snapshot JSON canonique),
 * pas du PDF binaire. Le PDF binaire ne peut pas embarquer son propre
 * hash (paradoxe d'auto-référence). L'empreinte du snapshot est en
 * revanche calculable indépendamment du rendu, donc affichable à la
 * fois dans le PDF et sur la page Show de la déclaration · les deux
 * affichent la même valeur, vérifiable par toute partie qui dispose
 * du snapshot persisté.
 *
 * **Doctrine immuabilité** · alignée sur ADR-0008/ADR-0015 · le snapshot
 * est figé au moment de la génération (markAsGenerated) et persisté
 * dans la colonne JSON `generated_snapshot_payload`. Toute modification
 * post-génération invaliderait l'empreinte · garantit l'intégrité
 * documentaire dans le temps.
 *
 * **Canonisation** · tri récursif des clés (ksortRecursive) + json_encode
 * avec JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES + JSON_THROW_ON_ERROR.
 * Garantit qu'un même payload sémantique produit toujours le même hash,
 * indépendamment de l'ordre d'insertion ou des particularités d'encodage.
 */
final class SnapshotHashCalculator
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function compute(array $payload): string
    {
        $sorted = self::ksortRecursive($payload);
        $json = json_encode(
            $sorted,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        return hash('sha256', $json);
    }

    /**
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    private static function ksortRecursive(array $array): array
    {
        ksort($array);
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::ksortRecursive($value);
            }
        }

        return $array;
    }
}
