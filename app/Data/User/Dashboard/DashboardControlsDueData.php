<?php

declare(strict_types=1);

namespace App\Data\User\Dashboard;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Regulatory controls reaching échéance across the active fleet, for the
 * Dashboard "Contrôles à échéance" panel.
 *
 * `count` is the total number of controls needing attention (overdue + due
 * today + due soon; paused/disabled and out-of-fleet controls excluded);
 * `items` carries the top few ranked by urgency (overdue first, soonest first).
 */
#[TypeScript]
final class DashboardControlsDueData extends Data
{
    /**
     * @param  list<DashboardControlsDueItemData>  $items
     */
    public function __construct(
        public int $count,
        #[DataCollectionOf(DashboardControlsDueItemData::class)]
        public array $items,
    ) {}

    public static function none(): self
    {
        return new self(count: 0, items: []);
    }
}
