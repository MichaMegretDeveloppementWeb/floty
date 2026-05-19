import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
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

type RelatedRule = { code: string; title: string };

/**
 * Pedagogical cross-references between rules ("See also: X").
 *
 * Scope: only the most essential category (tariff → routing). Tariff rules
 * (R-XXX-010/011/012/014) are abstract without the routing rule that explains
 * WHEN they apply (R-XXX-005 CO₂ method selection, R-XXX-013 pollutants categorisation).
 *
 * R-XXX-006 (PA fallback when NEDC is missing) is only referenced from the PA tariff,
 * where it explains why a vehicle might land there even though it should have been NEDC.
 * Referencing it from NEDC would be off-topic.
 *
 * This map is intentionally hardcoded in TS, not in the PHP classes: it is a UI navigation aid,
 * not part of the fiscal domain. The mapping is stable across years (same pairs in 2024/2025/2026).
 *
 * Targets absent from the registry (e.g. a referenced rule not registered for the year) are
 * silently filtered by `relatedRulesFor()`.
 */
const RELATED_RULES: Record<string, readonly string[]> = {
    // CO₂ tariffs → routing
    'R-2024-010': ['R-2024-005'],
    'R-2024-011': ['R-2024-005'],
    'R-2024-012': ['R-2024-005', 'R-2024-006'],
    'R-2025-010': ['R-2025-005'],
    'R-2025-011': ['R-2025-005'],
    'R-2025-012': ['R-2025-005', 'R-2025-006'],
    'R-2026-010': ['R-2026-005'],
    'R-2026-011': ['R-2026-005'],
    'R-2026-012': ['R-2026-005', 'R-2026-006'],
    // Pollutant tariffs → routing (pollutant categorisation).
    // Version-to-version pairing for 2026: each tariff references the categorisation of the same rank
    // (v1↔v1, bis↔bis), even when the splits do not occur on the same day (01/03 vs 01/09).
    // The pedagogical title of the target categorisation stays consistent with the source tariff period.
    'R-2024-014': ['R-2024-013'],
    'R-2025-014': ['R-2025-013'],
    'R-2026-014': ['R-2026-013'],
    'R-2026-014-bis': ['R-2026-013-bis'],
};

/**
 * State persisted into each history entry's `history.state` for the Fiscal Rules page.
 * Lets the browser Back button restore the previously visited tab AND scroll position.
 */
type HistoryStatePayload = {
    tab?: RuleTabValue;
    ruleCode?: string;
    scrollY?: number;
};

/**
 * Logic for the Fiscal Rules page (ADR-0022 v1.2).
 *
 * Tab/section organisation comes entirely from Inertia props (`FiscalRuleTabData[]` projected
 * from PHP enums RuleTab + RuleSection). Nothing is hardcoded TS-side anymore.
 *
 * Codes are filtered by section using `rule.pedagogicalContent.section` from each rule's DTO.
 * A rule without pedagogical content (tolerable only before the first seed) is silently ignored.
 *
 * Also exposes `relatedRulesFor()` and `navigateToRule()` for pedagogical cross-references
 * (see `RELATED_RULES` above), with persistence in `?tab=&rule=` and a new browser history entry
 * on each tab or target-rule change so Back restores the previous position.
 */
export function useFiscalRulesIndex(props: {
    rules: Rule[];
    tabs: Tab[];
}): {
    activeTab: Ref<RuleTabValue>;
    tabs: TabHeader[];
    rulesByCode: ComputedRef<Record<string, Rule>>;
    currentGroups: ComputedRef<SectionGroup[]>;
    flashedRuleCode: Ref<string | null>;
    relatedRulesFor: (code: string) => RelatedRule[];
    navigateToRule: (code: string) => void;
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

    const flashedRuleCode = ref<string | null>(null);
    // Re-entry guard: prevents the `activeTab` watcher from pushing into history again
    // when we already handled pushState manually (navigateToRule, popstate).
    let suppressTabWatcher = false;

    function relatedRulesFor(code: string): RelatedRule[] {
        const targets = RELATED_RULES[code] ?? [];

        return targets
            .map((c) => {
                const rule = rulesByCode.value[c];

                return rule
                    ? {
                          code: c,
                          title: rule.pedagogicalContent?.title ?? c,
                      }
                    : null;
            })
            .filter((r): r is RelatedRule => r !== null);
    }

    function buildUrlForState(
        tab: RuleTabValue,
        ruleCode?: string | null,
    ): string {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);

        if (ruleCode !== undefined && ruleCode !== null && ruleCode !== '') {
            url.searchParams.set('rule', ruleCode);
        } else {
            url.searchParams.delete('rule');
        }

        return url.pathname + url.search + url.hash;
    }

    function findTabForRule(code: string): RuleTabValue | null {
        const section = props.rules.find((r) => r.ruleCode === code)
            ?.pedagogicalContent?.section;

        if (section === undefined) {
            return null;
        }

        const tab = props.tabs.find((t) =>
            t.sections.some((s) => s.value === section),
        );

        return tab !== undefined ? (tab.value as RuleTabValue) : null;
    }

    function scrollAndFlash(code: string): void {
        const el = document.getElementById(`rule-${code}`);

        if (el === null) {
            return;
        }

        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        flashedRuleCode.value = code;
        setTimeout(() => {
            if (flashedRuleCode.value === code) {
                flashedRuleCode.value = null;
            }
        }, 1800);
    }

    /**
     * Snapshots the current scroll position onto the current history entry BEFORE pushing a new one.
     * Ensures Back restores the exact previous position.
     */
    function snapshotCurrentScroll(): void {
        const currentState =
            (history.state as HistoryStatePayload | null) ?? {};
        history.replaceState(
            { ...currentState, scrollY: window.scrollY },
            '',
        );
    }

    function navigateToRule(code: string): void {
        const targetTab = findTabForRule(code);

        if (targetTab === null) {
            return;
        }

        snapshotCurrentScroll();
        history.pushState(
            { tab: targetTab, ruleCode: code, scrollY: 0 },
            '',
            buildUrlForState(targetTab, code),
        );

        if (activeTab.value !== targetTab) {
            suppressTabWatcher = true;
            activeTab.value = targetTab;
        }

        void nextTick(() => scrollAndFlash(code));
    }

    function handlePopState(event: PopStateEvent): void {
        const state = (event.state as HistoryStatePayload | null) ?? {};
        const url = new URL(window.location.href);
        const tabFromState = state.tab;
        const tabFromUrl = url.searchParams.get('tab') ?? undefined;
        const targetTab = (tabFromState ?? tabFromUrl ?? props.tabs[0]?.value) as
            | RuleTabValue
            | undefined;

        if (
            targetTab !== undefined &&
            props.tabs.some((t) => t.value === targetTab) &&
            activeTab.value !== targetTab
        ) {
            suppressTabWatcher = true;
            activeTab.value = targetTab;
        }

        void nextTick(() => {
            const ruleCode =
                state.ruleCode ?? url.searchParams.get('rule') ?? null;

            if (ruleCode !== null && ruleCode !== '') {
                scrollAndFlash(ruleCode);
            } else if (typeof state.scrollY === 'number') {
                window.scrollTo({
                    top: state.scrollY,
                    left: 0,
                    behavior: 'auto',
                });
            }
        });
    }

    // Watches manual tab clicks: pushState to add a history entry, unless the change comes from
    // navigateToRule or popstate (which handle it themselves).
    watch(activeTab, (newTab) => {
        if (suppressTabWatcher) {
            suppressTabWatcher = false;

            return;
        }

        snapshotCurrentScroll();
        history.pushState(
            { tab: newTab, scrollY: 0 },
            '',
            buildUrlForState(newTab),
        );
    });

    onMounted(() => {
        // Restore from the initial URL (F5 reload, shared link, back from another page).
        const url = new URL(window.location.href);
        const initialTab = url.searchParams.get('tab');

        if (
            initialTab !== null &&
            props.tabs.some((t) => t.value === initialTab)
        ) {
            if (activeTab.value !== initialTab) {
                suppressTabWatcher = true;
                activeTab.value = initialTab as RuleTabValue;
            }
        }

        // Seeds an initial state on the current entry so future Back can restore the context
        // (otherwise `history.state` would be null on this entry).
        const currentState =
            (history.state as HistoryStatePayload | null) ?? {};
        history.replaceState(
            { ...currentState, tab: activeTab.value, scrollY: 0 },
            '',
        );

        const initialRule = url.searchParams.get('rule');

        if (initialRule !== null && initialRule !== '') {
            void nextTick(() => scrollAndFlash(initialRule));
        }

        window.addEventListener('popstate', handlePopState);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('popstate', handlePopState);
    });

    return {
        activeTab,
        tabs: tabHeaders,
        rulesByCode,
        currentGroups,
        flashedRuleCode,
        relatedRulesFor,
        navigateToRule,
    };
}
