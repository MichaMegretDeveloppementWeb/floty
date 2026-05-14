import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';

type Rule = App.Data.User.Fiscal.FiscalRuleListItemData;
type Tab = App.Data.User.Fiscal.FiscalRuleTabData;
type RuleTabValue = App.Enums.Fiscal.RuleTab;
type RuleSectionValue = App.Enums.Fiscal.RuleSection;

type TabHeader = { value: RuleTabValue; label: string };
type SectionGroup = {
    section: RuleSectionValue;
    title: string;
    subtitle: string;
    codes: string[];
};

/**
 * Logique de la page « Règles de calcul » (Phase 13 D5.12 · ADR-0022
 * finalisée v1.2). L'organisation tabs / sections vient désormais
 * intégralement des props Inertia (DTO `FiscalRuleTabData[]` projeté
 * depuis les enums PHP RuleTab + RuleSection) · plus rien de
 * hardcoded côté TS, plus de `fiscalRulesContent.ts`.
 *
 * Le filtrage des codes par section utilise `rule.pedagogicalContent.section`
 * (issu du DTO de chaque règle). Une règle sans contenu pédagogique
 * (cas tolérable seulement avant le 1er seed) est ignorée silencieusement.
 */
export function useFiscalRulesIndex(props: {
    rules: Rule[];
    tabs: Tab[];
}): {
    activeTab: Ref<RuleTabValue>;
    tabs: TabHeader[];
    rulesByCode: ComputedRef<Record<string, Rule>>;
    currentGroups: ComputedRef<SectionGroup[]>;
} {
    const activeTab = ref<RuleTabValue>(
        (props.tabs[0]?.value ?? 'calcul') as RuleTabValue,
    );

    const tabHeaders: TabHeader[] = props.tabs.map((t) => ({
        value: t.value,
        label: t.label,
    }));

    const rulesByCode = computed<Record<string, Rule>>(() => {
        const map: Record<string, Rule> = {};

        for (const r of props.rules) {
            map[r.ruleCode] = r;
        }

        return map;
    });

    const currentGroups = computed<SectionGroup[]>(() => {
        const activeTabData = props.tabs.find(
            (t) => t.value === activeTab.value,
        );

        if (activeTabData === undefined) {
            return [];
        }

        return activeTabData.sections.map((section) => {
            const codes = props.rules
                .filter(
                    (r) =>
                        r.pedagogicalContent?.section === section.value,
                )
                .map((r) => r.ruleCode);

            return {
                section: section.value,
                title: section.title,
                subtitle: section.subtitle,
                codes,
            };
        });
    });

    return {
        activeTab,
        tabs: tabHeaders,
        rulesByCode,
        currentGroups,
    };
}
