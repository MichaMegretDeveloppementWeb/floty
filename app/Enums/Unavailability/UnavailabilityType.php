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
 *   - CIBS art. L. 421-96 (« le véhicule immobilisé ou mis en fourrière
 *     à la demande des pouvoirs publics est réputé ne pas être affecté
 *     à des fins économiques »)
 *   - BOFiP BOI-AIS-MOB-10-30-10 § 50 (suspension CI + interdiction
 *     post-sinistre), § 60 (fourrière publique), § 190 (effet sur la
 *     proportion annuelle d'affectation)
 *   - C. route L. 325-1 → L. 325-1-2 (fourrière publique)
 *   - C. route L. 325-12 (fourrière privée - non réducteur)
 *   - C. route R. 322-6 (suspension CI)
 *   - C. route L. 327-4 / L. 327-5 (interdiction circulation post-sinistre)
 *
 * Note : la destruction VHU (certificat C. route R. 322-9) n'est PAS
 * dans cet enum - elle relève d'ADR-0018 cycle de vie véhicule
 * (`vehicles.exit_reason = Destroyed`). Idem pour le vol non résolu
 * définitif (`exit_reason = StolenUnrecovered`) - `theft` ici représente
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
     * Vrai ssi ce type réduit le numérateur du prorata fiscal - c.-à-d.
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
            self::AccidentNoCirculation => 'C. route L. 327-4 / L. 327-5 ; BOFiP BOI-AIS-MOB-10-30-10 § 50',
            self::PoundPublic => 'C. route L. 325-1 à L. 325-1-2 ; BOFiP BOI-AIS-MOB-10-30-10 § 60',
            self::CiSuspension => 'C. route R. 322-6 ; BOFiP BOI-AIS-MOB-10-30-10 § 50',
            self::AccidentRepair => 'BOFiP BOI-AIS-MOB-10-30-10 § 50 (réparation simple = taxable, voir Remarque § 50)',
            self::PoundPrivate => 'C. route L. 325-12 ; BOFiP BOI-AIS-MOB-10-30-10 § 60 (fourrière privée non réductrice — exclusion explicite)',
            self::Maintenance,
            self::TechnicalInspection => 'BOFiP BOI-AIS-MOB-10-30-10 (immobilisation opérationnelle = taxable, le § 50 ne couvre que les mises hors-circulation à la demande des pouvoirs publics)',
            self::Theft => 'doctrine V1 (vol non assimilé à mise hors-circulation administrative au sens de L. 421-96)',
            self::Other => 'indéterminé',
        };
    }
}
