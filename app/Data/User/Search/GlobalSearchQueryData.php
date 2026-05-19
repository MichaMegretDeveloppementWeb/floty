<?php

declare(strict_types=1);

namespace App\Data\User\Search;

use Spatie\LaravelData\Data;

/**
 * Input DTO for the global-search AJAX endpoint. Bound from HTTP query
 * params by Spatie Data (`GET /app/search?q=<query>`).
 *
 * `q` is required and trimmed to 2..100 characters. The upper bound is a
 * guard against abuse since results would be truncated past the useful
 * threshold anyway.
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
