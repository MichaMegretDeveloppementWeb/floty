<?php

declare(strict_types=1);

namespace App\Services\Control;

use App\Data\User\Control\ControlRecipientData;
use App\Models\ControlDefinition;
use App\Models\ControlReminderSettings;
use Illuminate\Database\Eloquent\Collection;

/**
 * Constant resolution context for one resolution pass (Chantier B). Built ONCE
 * (settings, default recipients, active control catalog) and reused for every
 * vehicle, so a fleet-wide scan (reminder dispatch) and the vehicle tab do not
 * re-query these per vehicle. Per-vehicle data (executions, overrides) is still
 * fetched per vehicle by {@see EffectiveControlResolver::resolveWithContext()}.
 */
final readonly class ControlResolutionContext
{
    /**
     * @param  array<int, ControlRecipientData>  $defaultRecipients
     * @param  array<string, string>  $baseRecipients  email => name
     * @param  Collection<int, ControlDefinition>  $definitions
     */
    public function __construct(
        public ControlReminderSettings $settings,
        public array $defaultRecipients,
        public array $baseRecipients,
        public Collection $definitions,
    ) {}
}
