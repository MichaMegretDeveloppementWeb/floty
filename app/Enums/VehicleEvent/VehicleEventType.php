<?php

declare(strict_types=1);

namespace App\Enums\VehicleEvent;

/**
 * Vehicle unavailability type (ADR-0016 rev. 1.1, 9 values). Three values reduce
 * the fiscal prorata numerator (R-2024-008); the six others are operational.
 *
 * Legal references:
 * - CIBS L. 421-96
 * - BOFiP BOI-AIS-MOB-10-30-10 § 50 / § 60 / § 190
 * - C. route L. 325-1 to L. 325-1-2, L. 325-12, R. 322-6, L. 327-4 / L. 327-5
 *
 * Definitive fleet exit cases (VHU destruction, unrecovered theft) live in
 * {@see App\Enums\Vehicle\VehicleExitReason} per ADR-0018.
 */
enum VehicleEventType: string
{
    // Fiscal reducers (3)
    case AccidentNoCirculation = 'accident_no_circulation';
    case PoundPublic = 'pound_public';
    case CiSuspension = 'ci_suspension';

    // Non-reducers (6)
    case Maintenance = 'maintenance';
    case TechnicalInspection = 'technical_inspection';
    case AccidentRepair = 'accident_repair';
    case PoundPrivate = 'pound_private';
    case Theft = 'theft';
    case Other = 'other';

    /**
     * Whether this type reduces the fiscal prorata numerator
     * (administrative or public-authority off-road status, BOFiP § 50 / § 60).
     */
    public function isFiscallyReductive(): bool
    {
        return match ($this) {
            self::AccidentNoCirculation,
            self::PoundPublic,
            self::CiSuspension => true,
            default => false,
        };
    }

    /**
     * Primary legal reference in short French form. Used in the R-2024-008 verdict payload.
     */
    public function legalReference(): string
    {
        return match ($this) {
            self::AccidentNoCirculation => 'C. route L. 327-4 / L. 327-5 ; BOFiP BOI-AIS-MOB-10-30-10 § 50',
            self::PoundPublic => 'C. route L. 325-1 à L. 325-1-2 ; BOFiP BOI-AIS-MOB-10-30-10 § 60',
            self::CiSuspension => 'C. route R. 322-6 ; BOFiP BOI-AIS-MOB-10-30-10 § 50',
            self::AccidentRepair => 'BOFiP BOI-AIS-MOB-10-30-10 § 50 (réparation simple = taxable, voir Remarque § 50)',
            self::PoundPrivate => 'C. route L. 325-12 ; BOFiP BOI-AIS-MOB-10-30-10 § 60 (fourrière privée non réductrice · exclusion explicite)',
            self::Maintenance,
            self::TechnicalInspection => 'BOFiP BOI-AIS-MOB-10-30-10 (immobilisation opérationnelle = taxable, le § 50 ne couvre que les mises hors-circulation à la demande des pouvoirs publics)',
            self::Theft => 'doctrine V1 (vol non assimilé à mise hors-circulation administrative au sens de L. 421-96)',
            self::Other => 'indéterminé',
        };
    }
}
