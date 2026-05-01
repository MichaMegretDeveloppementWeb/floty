<?php

declare(strict_types=1);

namespace App\Enums\Unavailability;

/**
 * Type d'indisponibilité d'un véhicule (ADR-0016 rev. 1.1, 9 valeurs).
 *
 * Trois cas réduisent le numérateur du prorata fiscal (R-2024-008) ;
 * les six autres représentent des indispos opérationnelles sans effet
 * fiscal. La colonne `unavailabilities.has_fiscal_impact` dénormalise
 * cette information pour le requêtage rapide ; un CHECK constraint en
 * base garantit la cohérence avec les 3 cases réducteurs ci-dessous.
 *
 * **Bases légales** :
 *   - CIBS art. L. 421-118 (assiette en temps d'utilisation effective)
 *   - BOFiP BOI-AIS-MOB-10-30-10 § 50 / § 60 / § 190
 *   - C. route L. 325-1 → L. 325-1-2 (fourrière publique)
 *   - C. route L. 325-12 (fourrière privée — non réducteur)
 *   - C. route R. 322-6 (suspension CI)
 *   - C. route L. 327-4 / L. 327-5 (interdiction circulation post-sinistre)
 *
 * Note : la destruction VHU (certificat C. route R. 322-9) n'est PAS
 * dans cet enum — elle relève d'ADR-0018 cycle de vie véhicule
 * (`vehicles.exit_reason = Destroyed`). Idem pour le vol non résolu
 * définitif (`exit_reason = StolenUnrecovered`) — `theft` ici représente
 * un vol récent susceptible de retour à la flotte.
 */
enum UnavailabilityType: string
{
    // Réducteurs fiscaux (3)
    case AccidentNoCirculation = 'accident_no_circulation';
    case PoundPublic = 'pound_public';
    case CiSuspension = 'ci_suspension';

    // Non réducteurs (6)
    case Maintenance = 'maintenance';
    case TechnicalInspection = 'technical_inspection';
    case AccidentRepair = 'accident_repair';
    case PoundPrivate = 'pound_private';
    case Theft = 'theft';
    case Other = 'other';

    /**
     * Vrai ssi ce type réduit le numérateur du prorata fiscal — c.-à-d.
     * caractérise une mise hors-circulation administrative ou par les
     * pouvoirs publics au sens BOFiP § 50 / § 60.
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
     * Référence de la base légale principale au format français court.
     * Utilisé dans le payload du verdict R-2024-008 et la fiche front.
     */
    public function legalReference(): string
    {
        return match ($this) {
            self::AccidentNoCirculation => 'C. route L. 327-4 / L. 327-5 ; BOFiP § 50',
            self::PoundPublic => 'C. route L. 325-1 à L. 325-1-2 ; BOFiP § 60 et § 190',
            self::CiSuspension => 'C. route R. 322-6 ; BOFiP § 50',
            self::AccidentRepair => 'BOFiP § 50 (réparation simple = taxable)',
            self::PoundPrivate => 'C. route L. 325-12 ; BOFiP § 60 (exclusion explicite)',
            self::Maintenance,
            self::TechnicalInspection => 'BOFiP § 50 (immobilisation opérationnelle = taxable)',
            self::Theft => 'doctrine V1 (vol non assimilé à mise hors-circulation administrative)',
            self::Other => 'indéterminé',
        };
    }
}
