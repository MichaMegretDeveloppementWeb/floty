import type { BadgeTone } from '@/types/ui';

type Rule = App.Data.User.Fiscal.FiscalRuleListItemData;
type Content = NonNullable<Rule['pedagogicalContent']>;

/**
 * Display helpers for a fiscal rule (ADR-0022 v1.2).
 *
 * Pedagogical content comes from `FiscalRuleListItemData.pedagogicalContent`,
 * projected from the rule's PHP class.
 *
 * If `rule` is undefined (transient race) or has no pedagogical content (tolerable
 * only before the first seed), `content` is undefined and the component must handle the fallback.
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
