import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import {
    cadreSectionsOrder,
    calculSectionsOrder,
    fiscalRulesContent2024,
    sectionTitles,
} from '@/data/fiscalRulesContent';
import type {
    RuleSection as RuleSectionKey,
    RuleTab,
} from '@/data/fiscalRulesContent';

type Rule = App.Data.User.Fiscal.FiscalRuleListItemData;

type Tab = { value: RuleTab; label: string; count?: number };
type SectionGroup = {
    section: RuleSectionKey;
    title: string;
    subtitle: string;
    codes: string[];
};

/**
 * Logique de la page « Règles de calcul » : indexation des règles
 * par code (résolution O(1) dans les sections), liste statique des
 * onglets (calcul / cadre), et calcul des groupes de sections en
 * fonction de l'onglet actif.
 */
export function useFiscalRulesIndex(props: { rules: Rule[] }): {
    activeTab: Ref<RuleTab>;
    tabs: Tab[];
    rulesByCode: ComputedRef<Record<string, Rule>>;
    currentGroups: ComputedRef<SectionGroup[]>;
} {
    const activeTab = ref<RuleTab>('calcul');

    const tabs: Tab[] = [
        { value: 'calcul', label: 'Calcul des taxes' },
        { value: 'cadre', label: 'Cadre & fonctionnement' },
    ];

    const rulesByCode = computed<Record<string, Rule>>(() => {
        const map: Record<string, Rule> = {};

        for (const r of props.rules) {
            map[r.ruleCode] = r;
        }

        return map;
    });

    const currentGroups = computed<SectionGroup[]>(() => {
        const sections =
            activeTab.value === 'calcul'
                ? calculSectionsOrder
                : cadreSectionsOrder;

        return sections.map((section) => {
            const codes = Object.entries(fiscalRulesContent2024)
                .filter(([, content]) => content.section === section)
                .map(([code]) => code);

            return {
                section,
                title: sectionTitles[section].title,
                subtitle: sectionTitles[section].subtitle,
                codes,
            };
        });
    });

    return { activeTab, tabs, rulesByCode, currentGroups };
}
