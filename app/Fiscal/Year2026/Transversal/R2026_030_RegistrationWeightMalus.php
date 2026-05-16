<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2026-030 · Malus au poids à l'immatriculation.
 *
 * **Règle documentaire-only · HORS PÉRIMÈTRE FLOTY.**
 *
 * Taxe ponctuelle à l'immatriculation d'un véhicule de tourisme dont
 * la masse en ordre de marche dépasse un seuil. Reconduction
 * R-2025-030 avec mention de l'éventuel abaissement de seuil 2026
 * acté par LF 2026 (à 1 500 kg pour les véhicules thermiques) ·
 * **non auditable précisément en Chrome live au 15/05/2026 sans
 * impact métier Floty** (règle inactive · le bailleur acquitte la
 * taxe, jamais les entreprises utilisatrices).
 *
 * **Redevable** · titulaire du certificat d'immatriculation (= le
 * bailleur dans le modèle Floty · entreprises utilisatrices jamais
 * redevables).
 *
 * **Audit Chrome live 15/05/2026** · section LEGISCTA000044598969
 * (Taxes sur l'immatriculation, L. 421-29 à L. 421-92) confirme
 * version 2026-01-01 active. Article-tête L. 421-71 « En vigueur
 * depuis le 01/01/2022 ». Barème détaillé porté par L. 421-72 à
 * L. 421-81-1 (paragraphe 5 « Taxe sur la masse en ordre de marche
 * des véhicules de tourisme ») · seuil et tarifs précis non
 * load-bearing pour Floty.
 */
final readonly class R2026_030_RegistrationWeightMalus implements InformativeRule
{
    use AnnualRuleTrait;

    public function ruleCode(): string
    {
        return 'R-2026-030';
    }

    public function isActive(): bool
    {
        return false;
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return "Malus au poids à l'immatriculation";
    }

    public function description(): string
    {
        return "Taxe ponctuelle acquittée par le titulaire du certificat d'immatriculation lors de la première immatriculation en France d'un véhicule de tourisme dont la masse en ordre de marche dépasse le seuil (1 600 kg en 2025 · seuil potentiellement abaissé à 1 500 kg en 2026 par LF 2026, à confirmer en Chrome live ciblé si l'utilisateur souhaite activer la règle). Barème de 10 €/kg au-dessus du seuil, avec abattement familial et exonération totale pour les véhicules électriques ou hydrogène. Hors périmètre de l'application · le bailleur (société de location) acquitte le malus, les entreprises utilisatrices ne sont jamais redevables.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 30;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-71 à L. 421-81-1',
                'url' => 'https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000044595989/LEGISCTA000044598969/2026-01-01/',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-20-40',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13927-PGP.html/identifiant=BOI-AIS-MOB-10-20-40-20250604',
                'consulted_at' => '2026-05-15',
            ],
            [
                'type' => 'NOTICE',
                'reference' => 'F35950',
                'url' => 'https://www.service-public.gouv.fr/particuliers/vosdroits/F35950',
                'consulted_at' => '2026-05-15',
            ],
        ];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2];
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::HorsPerimetre,
            section: RuleSection::TaxeConnexe,
            title: "Malus au poids à l'immatriculation",
            pitch: "Taxe ponctuelle payée à l'achat d'un véhicule neuf dont la masse en ordre de marche dépasse un seuil (1 600 kg en 2025, potentiellement 1 500 kg en 2026 selon LF 2026).",
            body: "Acquittée par le titulaire de la carte grise (le bailleur dans le modèle de l'application). Barème 10 €/kg au-dessus du seuil, avec abattement familial pour les familles nombreuses et exonération totale pour les véhicules électriques ou hydrogène. Hors périmètre de l'application · les entreprises utilisatrices ne sont pas redevables. L'éventuel abaissement de seuil 2026 par LF 2026 nécessite un audit Chrome live ciblé avant activation de la règle dans le périmètre Floty (non requis pour V1).",
            example: "Un SUV thermique de 1 900 kg immatriculé en 2026 est soumis à un malus de (1 900 - seuil) × 10 €/kg, acquitté par le bailleur lors de l'immatriculation. Avec seuil 1 600 kg · malus = 3 000 €. Avec seuil hypothétique 1 500 kg · malus = 4 000 €.",
        );
    }
}
