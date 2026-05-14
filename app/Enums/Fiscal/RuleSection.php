<?php

declare(strict_types=1);

namespace App\Enums\Fiscal;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Section pédagogique d'une règle fiscale dans son onglet (Phase 13
 * D5.12 · complément ADR-0022). Détermine son groupage à l'affichage
 * et porte le titre + sous-titre de la section parente.
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

    public function title(): string
    {
        return match ($this) {
            self::Aiguillage => "Comment l'application choisit le bon barème pour chaque véhicule",
            self::Bareme => 'Barèmes et tarifs applicables',
            self::Exoneration => "Exonérations applicables dans l'application",
            self::ExonerationInactive => "Exonérations, abattements et minorations prévus par la loi mais non applicables dans l'application",
            self::CadreImplicite => 'Règles implicites du calcul',
            self::CadreEvenement => 'Règles de gestion des évènements véhicule',
            self::CadreInterne => "Règles de fonctionnement interne de l'application",
            self::CadreDeclaratif => 'Modalités de déclaration et de paiement',
            self::TaxeConnexe => 'Taxes connexes véhicules hors périmètre Floty',
        };
    }

    public function subtitle(): string
    {
        return match ($this) {
            self::Aiguillage => "Trois étapes d'aiguillage : taxable ou non, quel barème CO₂, quelle catégorie polluants.",
            self::Bareme => 'Chiffres bruts utilisés pour calculer le tarif annuel plein de chaque taxe.',
            self::Exoneration => 'Règles qui annulent ou réduisent le montant dû pour une attribution donnée.',
            self::ExonerationInactive => "Règles fiscales de réduction (exonérations, abattements, minorations) que la loi prévoit mais que l'application n'applique pas, faute de cas d'usage Floty ou par choix produit.",
            self::CadreImplicite => 'Évidences du mécanisme fiscal, énoncées ici pour mémoire.',
            self::CadreEvenement => "Comment l'application traite les indisponibilités, sorties de flotte et données manquantes.",
            self::CadreInterne => "Mécanismes de validation et d'audit transparents pour l'utilisateur.",
            self::CadreDeclaratif => "Comment l'entreprise déclare et acquitte les taxes annuelles · formulaires, dates, paiement, état récapitulatif.",
            self::TaxeConnexe => "Taxes applicables aux véhicules d'entreprise mais hors du périmètre métier de Floty (malus à l'immatriculation, taxes carte grise, TAI verdissement des grosses flottes, taxe véhicules lourds, etc.). Documentées pour exhaustivité.",
        };
    }
}
