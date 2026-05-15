<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use stdClass;

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
 * **Canonisation** ·
 *   1. Tri récursif des clés alphabétiquement (ksortRecursive) · même
 *      payload sémantique → même ordre des clés
 *   2. Normalisation récursive `[]` → `(object) stdClass` · garantit
 *      qu'un array vide PHP et un objet vide JSON produisent le même
 *      hash (Lot 5 D8 · F-19D2-011 · ferme le bord PHP-vs-JSON sur
 *      sections optionnelles)
 *   3. `json_encode` avec `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
 *      | JSON_THROW_ON_ERROR`
 *
 * **Cache statique intra-requête** (Lot 5 D8 · F-19D2-007) · clé = JSON
 * canonique sérialisé. Évite de recalculer SHA-256 sur le même payload
 * lorsque plusieurs hydratations Spatie de `FiscalDeclarationData::fromModel`
 * passent dans la même requête HTTP (page Show qui charge N déclarations
 * historiques). Le cache est scopé au lifecycle PHP du worker · pas de
 * contamination cross-requête. La méthode {@see flush()} permet aux tests
 * de réinitialiser entre cas.
 */
final class SnapshotHashCalculator
{
    /** @var array<string, string> */
    private static array $cache = [];

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function compute(array $payload): string
    {
        $canonical = self::canonicalize($payload);
        $json = json_encode(
            $canonical,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        return self::$cache[$json] ??= hash('sha256', $json);
    }

    /**
     * Vide le cache statique · à utiliser dans les tests qui assertent
     * sur la mémoïsation ou qui veulent isoler chaque cas.
     */
    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Canonicalise récursivement une valeur ·
     *   - Array vide → `stdClass` vide (cohérence PHP-vs-JSON)
     *   - Array associatif → tri par clé ascendant + récursion
     *   - Array de liste → récursion sur les éléments (ordre préservé)
     *   - Scalaire → inchangé
     */
    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($value === []) {
            return new stdClass;
        }

        if (array_is_list($value)) {
            return array_map(self::canonicalize(...), $value);
        }

        ksort($value);
        $normalized = [];
        foreach ($value as $key => $sub) {
            $normalized[$key] = self::canonicalize($sub);
        }

        return $normalized;
    }
}
