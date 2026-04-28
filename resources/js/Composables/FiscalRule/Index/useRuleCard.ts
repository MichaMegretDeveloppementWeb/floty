import { fiscalRulesContent2024 } from '@/data/fiscalRulesContent';
import type { RuleContent } from '@/data/fiscalRulesContent';
import type { BadgeTone } from '@/types/ui';

type Rule = App.Data.User.Fiscal.FiscalRuleListItemData;

/**
 * Helpers d'affichage d'une règle fiscale (taxes concernées, contenu
 * pédagogique enrichi côté front via `fiscalRulesContent2024`).
 *
 * Le `content` reçoit le `code` au moment de l'appel (pas de Ref) :
 * la page parent ne change pas le code dynamiquement — le composant
 * est re-monté quand le code change.
 */
export function useRuleCard(props: { code: string }): {
    taxLabel: Record<string, string>;
    taxBadgeTone: (taxes: Rule['taxesConcerned']) => BadgeTone;
    content: RuleContent | undefined;
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

    const content = fiscalRulesContent2024[props.code];

    return { taxLabel, taxBadgeTone, content };
}
