import type { BadgeTone } from '@/types/ui';

type Rule = App.Data.User.Fiscal.FiscalRuleListItemData;
type Content = NonNullable<Rule['pedagogicalContent']>;

/**
 * Helpers d'affichage d'une règle fiscale (Phase 13 D5.12 · ADR-0022
 * finalisée v1.2). Le contenu pédagogique vient désormais du DTO
 * `FiscalRuleListItemData.pedagogicalContent` projeté depuis la classe
 * PHP de la règle · plus de `fiscalRulesContent.ts`.
 *
 * Si `rule` est undefined (race transitoire) ou si la règle n'a pas
 * de contenu pédagogique (cas tolérable seulement avant le 1er seed),
 * `content` vaut undefined · le composant doit gérer ce fallback.
 */
export function useRuleCard(props: { rule: Rule | undefined }): {
    taxLabel: Record<string, string>;
    taxBadgeTone: (taxes: Rule['taxesConcerned']) => BadgeTone;
    content: Content | undefined;
} {
    const taxLabel: Record<string, string> = {
        co2: 'CO₂',
        pollutants: 'Polluants',
    };

    const taxBadgeTone = (taxes: Rule['taxesConcerned']): BadgeTone => {
        if (taxes.includes('co2') && taxes.includes('pollutants')) {
            return 'blue';
        }

        if (taxes.includes('co2')) {
            return 'blue';
        }

        if (taxes.includes('pollutants')) {
            return 'amber';
        }

        return 'slate';
    };

    const content = (props.rule?.pedagogicalContent ?? undefined) as
        | Content
        | undefined;

    return { taxLabel, taxBadgeTone, content };
}
