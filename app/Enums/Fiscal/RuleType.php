<?php

declare(strict_types=1);

namespace App\Enums\Fiscal;

/**
 * The five functional subtypes of a Floty fiscal rule (ADR-0006 § 1).
 *
 * Each catalog rule (R-{year}-{nnn}) implements exactly one subtype, which
 * determines its role in the pipeline (ADR-0006 § 2):
 *
 * - `Classification`: produces a qualification from vehicle characteristics.
 * - `Tariff`: produces a full annual tariff from characteristics.
 * - `Exemption`: checks an exoneration condition (possibly stateful).
 * - `Abatement`: alters an input characteristic before tariffing.
 * - `Transversal`: cross-cutting operations (proration, rounding, unavailabilities, history).
 */
enum RuleType: string
{
    case Classification = 'classification';
    case Tariff = 'tariff';
    case Exemption = 'exemption';
    case Abatement = 'abatement';
    case Transversal = 'transversal';
}
