<?php

declare(strict_types=1);

namespace App\Data\User\Search;

use Spatie\LaravelData\Data;

/**
 * DTO d'entrée pour l'endpoint AJAX de la barre de recherche globale
 * (palette ⌘K, V1.1). Auto-bindée depuis les query params HTTP par
 * Spatie Data (`GET /app/search?q=<query>`).
 *
 * Validation · `q` requis, longueur 2 à 100 caractères (le `trim`
 * normalise les espaces autour avant comptage).
 *
 * Borne supérieure 100 · garde-fou contre les abus (l'utilisateur n'a
 * pas de raison de saisir plus, et le service tronquerait de toute
 * façon les résultats au-delà du seuil utile).
 */
final class GlobalSearchQueryData extends Data
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 100;

    public function __construct(public string $q)
    {
        $this->q = trim($this->q);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:'.self::MIN_LENGTH, 'max:'.self::MAX_LENGTH],
        ];
    }
}
