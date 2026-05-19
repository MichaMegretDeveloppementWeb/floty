<?php

declare(strict_types=1);

namespace App\Enums\Fiscal;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Pedagogical section of a fiscal rule within its tab. Drives display grouping
 * and provides the parent section's title and subtitle.
 */
#[TypeScript]
enum RuleSection: string
{
    case Aiguillage = 'aiguillage';
    case Bareme = 'bareme';
    case Exoneration = 'exoneration';
    case ExonerationInactive = 'exoneration-inactive';
    case CadreImplicite = 'cadre-implicite';
    case CadreEvenement = 'cadre-evenement';
    case CadreInterne = 'cadre-interne';
    case CadreDeclaratif = 'cadre-declaratif';
    case TaxeConnexe = 'taxe-connexe';

    /**
     * French title for display.
     */
    public function title(): string
    {
        return match ($this) {
            self::Aiguillage => 'Sélection du barème applicable',
            self::Bareme => 'Barèmes et tarifs',
            self::Exoneration => 'Exonérations appliquées',
            self::ExonerationInactive => 'Exonérations légales non appliquées',
            self::CadreImplicite => 'Règles implicites',
            self::CadreEvenement => 'Événements véhicule',
            self::CadreInterne => 'Fonctionnement interne',
            self::CadreDeclaratif => 'Déclaration et paiement',
            self::TaxeConnexe => 'Taxes véhicule hors Floty',
        };
    }

    /**
     * French subtitle for display.
     */
    public function subtitle(): string
    {
        return match ($this) {
            self::Aiguillage => "Trois étapes d'aiguillage qui choisissent les bons barèmes en fonction des caractéristiques du véhicule · taxable ou non, quel barème CO₂, quelle catégorie polluants.",
            self::Bareme => 'Chiffres bruts utilisés pour calculer le tarif annuel plein de chaque taxe.',
            self::Exoneration => 'Règles qui annulent ou réduisent le montant dû pour une attribution donnée.',
            self::ExonerationInactive => "Exonérations, abattements et minorations prévus par la loi mais que Floty n'applique pas (cas d'usage absent ou choix produit). Documentés pour transparence.",
            self::CadreImplicite => 'Évidences du mécanisme fiscal, énoncées ici pour mémoire.',
            self::CadreEvenement => "Comment l'application traite les indisponibilités, sorties de flotte et données manquantes.",
            self::CadreInterne => "Mécanismes de validation et d'audit transparents pour l'utilisateur.",
            self::CadreDeclaratif => "Comment l'entreprise déclare et acquitte les taxes annuelles · formulaires, dates, paiement, état récapitulatif.",
            self::TaxeConnexe => "Taxes véhicule prévues par la loi mais hors périmètre Floty (malus immatriculation, taxes carte grise, TAI verdissement, taxe véhicules lourds). Acquittées par le bailleur ou directement par l'entreprise via son comptable · documentées pour exhaustivité.",
        };
    }
}
