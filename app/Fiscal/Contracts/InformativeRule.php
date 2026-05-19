<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use Database\Seeders\FiscalRulesSeeder;

/**
 * Marker interface for documentary-only fiscal rules (ADR-0022).
 *
 * Some fiscal rules do not participate in the calculating pipeline but
 * still carry domain knowledge (legal framework, architectural
 * principle, UI guard). Examples for 2024: R-2024-001 taxpayer,
 * R-2024-009 mid-year decommissioning, R-2024-024 Crit'Air guard.
 *
 * A separate contract (vs a boolean flag on `FiscalRule`) lets static
 * typing prevent accidental inclusion of documentary rules in
 * `FiscalRuleRegistry::register()`, which accepts only
 * `list<class-string<FiscalRule>>`. The pipeline stays strictly
 * isolated.
 *
 * Documentary rules share the same metadata shape as pipeline rules
 * (code, name, description, legal basis with official URLs, type,
 * concerned taxes, applicability window, display order, active flag).
 * They are consumed only by
 * {@see FiscalRulesSeeder} to populate the
 * `fiscal_rules` index for the "Règles de calcul" page.
 */
interface InformativeRule extends FiscalRule {}
