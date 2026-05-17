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
 * Cross-références pédagogiques entre règles (« Voir aussi · X »).
 *
 * Source · uniquement la **catégorie 1** (barèmes → aiguillage), la
 * plus essentielle · les barèmes (R-XXX-010/011/012/014) sont abstraits
 * sans la règle d'aiguillage qui explique **quand** ils s'appliquent
 * (R-XXX-005 sélection méthode CO₂, R-XXX-013 catégorisation polluants).
 *
 * R-XXX-006 (fallback PA quand NEDC manquant) est uniquement référencé
 * depuis le barème PA · il y explique pourquoi un véhicule peut y
 * atterrir alors qu'il aurait dû être en NEDC. Référencé depuis NEDC
 * il serait hors-sujet (le fallback ne décrit pas le barème NEDC).
 *
 * Cette map est volontairement hardcodée TS et **pas** dans les classes
 * PHP · c'est une aide à la navigation UI, pas du domaine fiscal. Le
 * mapping est stable entre années (mêmes pairs en 2024/2025/2026).
 *
 * Les cibles absentes du registry (cas où une règle référencée n'aurait
 * pas été registered pour l'année) sont filtrées silencieusement par
 * `relatedRulesFor()`.
 */
const RELATED_RULES: Record<string, readonly string[]> = {
    // Barèmes CO₂ → Aiguillage
    'R-2024-010': ['R-2024-005'],
    'R-2024-011': ['R-2024-005'],
    'R-2024-012': ['R-2024-005', 'R-2024-006'],
    'R-2025-010': ['R-2025-005'],
    'R-2025-011': ['R-2025-005'],
    'R-2025-012': ['R-2025-005', 'R-2025-006'],
    'R-2026-010': ['R-2026-005'],
    'R-2026-011': ['R-2026-005'],
    'R-2026-012': ['R-2026-005', 'R-2026-006'],
    // Barèmes polluants → Aiguillage (catégorisation polluants).
    // Pairing version-à-version pour 2026 · chaque tarif référence la
    // catégorisation du même rang (v1↔v1, bis↔bis), même si les
    // scissions ne tombent pas le même jour (01/03 vs 01/09). Le titre
    // de la catégorisation cible cohère ainsi avec la période du tarif
    // source côté pédagogie.
    'R-2024-014': ['R-2024-013'],
    'R-2025-014': ['R-2025-013'],
    'R-2026-014': ['R-2026-013'],
    'R-2026-014-bis': ['R-2026-013-bis'],
};

/**
 * État persisté dans `history.state` de chaque entrée d'historique pour
 * la page Règles de calcul. Permet au bouton Back du navigateur de
 * restaurer fidèlement l'onglet ET la position de scroll de la règle
 * visitée précédemment.
 */
type HistoryStatePayload = {
    tab?: RuleTabValue;
    ruleCode?: string;
    scrollY?: number;
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
 *
 * Expose aussi `relatedRulesFor()` et `navigateToRule()` pour les
 * cross-références pédagogiques (cf. constante `RELATED_RULES` plus
 * haut), avec persistance dans `?tab=&rule=` et entrée d'historique
 * navigateur à chaque changement d'onglet ou de règle cible (le bouton
 * Back ramène à la position précédente).
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
    // Garde anti-boucle · empêche le watcher `activeTab` de re-pousser
    // dans l'historique quand on a déjà géré le pushState manuellement
    // (navigateToRule, popstate).
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
     * Sauve la position de scroll courante sur l'entrée d'historique
     * actuelle, **avant** de pousser une nouvelle entrée. Garantit que
     * le bouton Back restaure exactement où l'utilisateur était.
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

    // Watch des clics manuels sur les onglets · pushState pour ajouter
    // une entrée d'historique. Sauf si le changement vient de
    // navigateToRule ou popstate (qui s'en chargent eux-mêmes).
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
        // Restauration depuis l'URL initiale (rechargement F5, lien
        // partagé, retour navigateur depuis une autre page).
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

        // Pose un état initial sur l'entrée courante pour que le back
        // futur puisse en restaurer le contexte (sinon `history.state`
        // serait null sur cette entrée).
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
