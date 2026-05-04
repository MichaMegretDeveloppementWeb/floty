<?php

declare(strict_types=1);

namespace Tests\Feature\Concerns;

use Inertia\Testing\AssertableInertia;

/**
 * Helper d'assertion pour les pages Index server-side migrées par
 * ADR-0020. Vérifie la shape `{data: [...], meta: {currentPage,
 * lastPage, perPage, total, from, to}}` et offre des assertions de
 * meta granulaires.
 *
 * Usage type :
 *
 *     $this->assertPaginatedShape(
 *         $page,
 *         'drivers',
 *         expectedDataCount: 3,
 *         expectedMeta: ['total' => 3, 'currentPage' => 1, 'perPage' => 20],
 *     );
 */
trait AssertsPaginatedIndex
{
    /**
     * @param  array<string, int|null>  $expectedMeta  Subset de meta à
     *                                                 asserter (currentPage,
     *                                                 lastPage, perPage,
     *                                                 total, from, to). Les
     *                                                 clés absentes ne sont
     *                                                 pas vérifiées.
     */
    protected function assertPaginatedShape(
        AssertableInertia $page,
        string $key,
        int $expectedDataCount,
        array $expectedMeta = [],
    ): AssertableInertia {
        return $page->has($key, function (AssertableInertia $list) use ($expectedDataCount, $expectedMeta): void {
            $list->has('data', $expectedDataCount)
                ->has('meta', function (AssertableInertia $meta) use ($expectedMeta): void {
                    $meta->has('currentPage')
                        ->has('lastPage')
                        ->has('perPage')
                        ->has('total')
                        ->has('from')
                        ->has('to');

                    foreach ($expectedMeta as $metaKey => $value) {
                        $meta->where($metaKey, $value);
                    }
                });
        });
    }
}
