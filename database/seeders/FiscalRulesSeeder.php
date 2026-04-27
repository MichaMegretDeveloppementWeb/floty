<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Models\FiscalRule;
use Illuminate\Database\Seeder;

/**
 * Seeder des 24 règles du catalogue fiscal 2024 Floty.
 *
 * Ne seed que les **métadonnées** consultables (cf. 02-schema-fiscal.md § 1) :
 * la logique de calcul vit dans les classes PHP sous `app/Fiscal/Rules/2024/`
 * (à implémenter en phase 10). ADR-0002 : alimentation par seeder uniquement.
 *
 * Source : `project-management/taxes-rules/2024.md`.
 */
final class FiscalRulesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->rules() as $rule) {
            $enriched = $this->enrich($rule);

            FiscalRule::updateOrCreate(
                [
                    'rule_code' => $enriched['rule_code'],
                    'fiscal_year' => $enriched['fiscal_year'],
                ],
                $enriched,
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rules(): array
    {
        $both = [TaxType::Co2->value, TaxType::Pollutants->value];
        $co2 = [TaxType::Co2->value];
        $pol = [TaxType::Pollutants->value];

        $cibs = fn (string $article): array => ['type' => 'CIBS', 'article' => $article];

        return [
            [
                'rule_code' => 'R-2024-001',
                'name' => 'Redevable et fait générateur',
                'description' => "Définit qui est redevable de la taxe (entreprise utilisatrice) et le fait générateur (affectation d'un véhicule à des fins économiques).",
                'rule_type' => RuleType::Transversal,
                'taxes_concerned' => $both,
                'legal_basis' => [
                    $cibs('L. 421-95'),
                    $cibs('L. 421-99'),
                ],
                'display_order' => 1,
            ],
            [
                'rule_code' => 'R-2024-002',
                'name' => 'Prorata journalier (366 jours en 2024)',
                'description' => 'Mécanique du prorata journalier : tarif annuel plein × (jours affectés / 366) en 2024.',
                'rule_type' => RuleType::Transversal,
                'taxes_concerned' => $both,
                'legal_basis' => [
                    $cibs('L. 421-107'),
                ],
                'display_order' => 2,
            ],
            [
                'rule_code' => 'R-2024-003',
                'name' => "Méthode d'arrondi half-up commercial",
                'description' => 'Arrondi au centime commercial sur le montant total final du redevable, pas par calcul intermédiaire.',
                'rule_type' => RuleType::Transversal,
                'taxes_concerned' => $both,
                'legal_basis' => [
                    $cibs('L. 131-1'),
                ],
                'display_order' => 3,
            ],
            [
                'rule_code' => 'R-2024-004',
                'name' => 'Qualification M1 / N1',
                'description' => 'Classification du type fiscal du véhicule : frontière M1 (VP) vs N1 (VU), cas particuliers N1 ≥ 5 places.',
                'rule_type' => RuleType::Classification,
                'taxes_concerned' => $both,
                'legal_basis' => [
                    $cibs('L. 421-2'),
                ],
                'display_order' => 4,
            ],
            [
                'rule_code' => 'R-2024-005',
                'name' => 'Sélection du barème CO₂',
                'description' => 'Détermine le barème applicable (WLTP / NEDC / PA) à partir des caractéristiques véhicule.',
                'rule_type' => RuleType::Classification,
                'taxes_concerned' => $co2,
                'legal_basis' => [
                    $cibs('L. 421-119-1'),
                ],
                'display_order' => 5,
            ],
            [
                'rule_code' => 'R-2024-006',
                'name' => 'Bascule sur barème PA (CO₂ manquant)',
                'description' => 'Fallback vers le barème Puissance Administrative si la donnée CO₂ est manquante.',
                'rule_type' => RuleType::Classification,
                'taxes_concerned' => $co2,
                'legal_basis' => [
                    $cibs('L. 421-119-1'),
                ],
                'display_order' => 6,
            ],
            [
                'rule_code' => 'R-2024-007',
                'name' => 'Historisation des caractéristiques véhicule',
                'description' => "Application de la version effective des caractéristiques fiscales à chaque jour d'affectation.",
                'rule_type' => RuleType::Transversal,
                'taxes_concerned' => $both,
                'legal_basis' => [
                    $cibs('L. 421-164'),
                ],
                'display_order' => 7,
            ],
            [
                'rule_code' => 'R-2024-008',
                'name' => 'Impact des indisponibilités fourrière',
                'description' => "Les jours d'indisponibilité fourrière sont déduits du numérateur du prorata. Règle issue de la doctrine BOFiP, sans article CIBS direct.",
                'rule_type' => RuleType::Transversal,
                'taxes_concerned' => $both,
                'legal_basis' => [],
                'display_order' => 8,
            ],
            [
                'rule_code' => 'R-2024-009',
                'name' => "Mise hors-service en cours d'année",
                'description' => "Gestion des véhicules sortis de flotte (vente, destruction) — prorata jusqu'à la date de sortie. Règle d'UX produit, sans fondement légal direct.",
                'rule_type' => RuleType::Transversal,
                'taxes_concerned' => $both,
                'legal_basis' => [],
                'display_order' => 9,
            ],
            [
                'rule_code' => 'R-2024-010',
                'name' => 'Barème WLTP 2024',
                'description' => 'Tarif progressif par tranches sur les émissions CO₂ WLTP.',
                'rule_type' => RuleType::Tariff,
                'taxes_concerned' => $co2,
                'legal_basis' => [
                    $cibs('L. 421-120'),
                ],
                'display_order' => 10,
            ],
            [
                'rule_code' => 'R-2024-011',
                'name' => 'Barème NEDC 2024',
                'description' => 'Tarif progressif par tranches sur les émissions CO₂ NEDC (véhicules antérieurs à WLTP).',
                'rule_type' => RuleType::Tariff,
                'taxes_concerned' => $co2,
                'legal_basis' => [
                    $cibs('L. 421-121'),
                ],
                'display_order' => 11,
            ],
            [
                'rule_code' => 'R-2024-012',
                'name' => 'Barème Puissance Administrative 2024',
                'description' => 'Tarif forfaitaire sur la puissance fiscale (véhicules pré-2004 ou sans CO₂).',
                'rule_type' => RuleType::Tariff,
                'taxes_concerned' => $co2,
                'legal_basis' => [
                    $cibs('L. 421-122'),
                ],
                'display_order' => 12,
            ],
            [
                'rule_code' => 'R-2024-013',
                'name' => 'Catégorisation polluants',
                'description' => 'Classement du véhicule dans les catégories E / 1 / « les plus polluants » selon motorisation et norme Euro.',
                'rule_type' => RuleType::Classification,
                'taxes_concerned' => $pol,
                'legal_basis' => [
                    $cibs('L. 421-134'),
                ],
                'display_order' => 13,
            ],
            [
                'rule_code' => 'R-2024-014',
                'name' => 'Tarif forfaitaire polluants 2024',
                'description' => "Tarif annuel forfaitaire par catégorie d'émissions (E = 0 € / 1 = 100 € / plus polluants = 500 €).",
                'rule_type' => RuleType::Tariff,
                'taxes_concerned' => $pol,
                'legal_basis' => [
                    $cibs('L. 421-135'),
                ],
                'display_order' => 14,
            ],
            [
                'rule_code' => 'R-2024-015',
                'name' => 'Exonération handicap',
                'description' => 'Véhicules accessibles aux personnes à mobilité réduite — exonération totale CO₂ et polluants.',
                'rule_type' => RuleType::Exemption,
                'taxes_concerned' => $both,
                'legal_basis' => [
                    $cibs('L. 421-123'),
                    $cibs('L. 421-136'),
                ],
                'display_order' => 15,
            ],
            [
                'rule_code' => 'R-2024-016',
                'name' => 'Exonération électrique / hydrogène (CO₂)',
                'description' => 'Véhicules électriques, hydrogène, ou électrique + hydrogène exclusifs — exonération totale CO₂.',
                'rule_type' => RuleType::Exemption,
                'taxes_concerned' => $co2,
                'legal_basis' => [
                    $cibs('L. 421-124'),
                ],
                'display_order' => 16,
            ],
            [
                'rule_code' => 'R-2024-017',
                'name' => 'Exonération hybride conditionnelle (CO₂)',
                'description' => "Véhicules hybrides 2024 respectant des seuils de CO₂ et d'ancienneté — exonération CO₂ totale.",
                'rule_type' => RuleType::Exemption,
                'taxes_concerned' => $co2,
                'legal_basis' => [
                    $cibs('L. 421-125'),
                ],
                'display_order' => 17,
            ],
            [
                'rule_code' => 'R-2024-018',
                'name' => "Exonération organisme d'intérêt général",
                'description' => 'OIG (CGI art. 261, 7°) — exonération CO₂ et polluants. INACTIVE par défaut en V1.',
                'rule_type' => RuleType::Exemption,
                'taxes_concerned' => $both,
                'legal_basis' => [
                    $cibs('L. 421-126'),
                    $cibs('L. 421-138'),
                ],
                'display_order' => 18,
                'is_active' => false,
            ],
            [
                'rule_code' => 'R-2024-019',
                'name' => 'Exonération entreprise individuelle',
                'description' => 'Entreprise individuelle relevant du régime micro-entreprise. INACTIVE par défaut en V1.',
                'rule_type' => RuleType::Exemption,
                'taxes_concerned' => $both,
                'legal_basis' => [
                    $cibs('L. 421-127'),
                    $cibs('L. 421-139'),
                ],
                'display_order' => 19,
                'is_active' => false,
            ],
            [
                'rule_code' => 'R-2024-020',
                'name' => 'Exonération loueur — redevable = entreprise utilisatrice',
                'description' => "Fondamentale pour Floty : le loueur n'est pas redevable, ce sont les entreprises utilisatrices qui le sont.",
                'rule_type' => RuleType::Exemption,
                'taxes_concerned' => $both,
                'legal_basis' => [
                    $cibs('L. 421-128'),
                    $cibs('L. 421-140'),
                ],
                'display_order' => 20,
            ],
            [
                'rule_code' => 'R-2024-021',
                'name' => 'Exonération LCD (cumul ≤ 30 jours par couple)',
                'description' => "Location de courte durée : cumul annuel d'affectation par couple (véhicule, entreprise) ≤ 30 jours → exonération totale.",
                'rule_type' => RuleType::Exemption,
                'taxes_concerned' => $both,
                'legal_basis' => [
                    $cibs('L. 421-129'),
                    $cibs('L. 421-141'),
                ],
                'display_order' => 21,
            ],
            [
                'rule_code' => 'R-2024-022',
                'name' => 'Exonérations à activité (transport public, agricole…)',
                'description' => 'Transport public de personnes, agricole/forestier, enseignement de la conduite, compétitions sportives. INACTIVES par défaut.',
                'rule_type' => RuleType::Exemption,
                'taxes_concerned' => $both,
                'legal_basis' => [
                    $cibs('L. 421-130'),
                    $cibs('L. 421-131'),
                    $cibs('L. 421-132'),
                    $cibs('L. 421-142'),
                    $cibs('L. 421-143'),
                    $cibs('L. 421-144'),
                ],
                'display_order' => 22,
                'is_active' => false,
            ],
            [
                'rule_code' => 'R-2024-023',
                'name' => 'Aucun abattement isolé applicable en 2024',
                'description' => "2024 : aucun abattement isolé (ex. E85) applicable. Placeholder pour 2025+ où l'abattement E85 pourrait apparaître.",
                'rule_type' => RuleType::Abatement,
                'taxes_concerned' => $both,
                'legal_basis' => [],
                'display_order' => 23,
            ],
            [
                'rule_code' => 'R-2024-024',
                'name' => "Garde-fou Crit'Air",
                'description' => "Contrôle de cohérence entre la catégorie polluants calculée et la vignette Crit'Air attendue (alerte, non bloquant). Règle issue de la doctrine BOFiP, sans article CIBS direct.",
                'rule_type' => RuleType::Transversal,
                'taxes_concerned' => $pol,
                'legal_basis' => [],
                'display_order' => 24,
                'code_reference' => 'resources/js/Composables/Vehicle/useCritAirCheck.ts',
            ],
        ];
    }

    /**
     * Complète une ligne de règle avec les valeurs par défaut communes.
     * (pas utilisé — inlined dans run() via updateOrCreate qui accepte la map.)
     *
     * Enrichi sur chaque entrée :
     *   - fiscal_year = 2024
     *   - applicability_start = '2024-01-01', applicability_end = '2024-12-31'
     *   - code_reference = app/Fiscal/Rules/2024/...  (placeholder phase 10)
     *   - is_active par défaut true (sauf override explicite)
     */
    private function enrich(array $rule): array
    {
        return [
            ...$rule,
            'fiscal_year' => 2024,
            'applicability_start' => '2024-01-01',
            'applicability_end' => '2024-12-31',
            'code_reference' => $rule['code_reference']
                ?? 'app/Fiscal/Year2024/'.str_replace('-', '_', $rule['rule_code']).'.php',
            'is_active' => $rule['is_active'] ?? true,
        ];
    }
}
