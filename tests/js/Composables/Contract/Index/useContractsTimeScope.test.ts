import { describe, expect, it, vi } from 'vitest';
import { computed, ref } from 'vue';
import type { ContractFilters } from '@/Composables/Contract/Index/useContractsTable';
import {
    resolveInitialScopeMode,
    useContractsTimeScope,
} from '@/Composables/Contract/Index/useContractsTimeScope';
import type { ServerTableState } from '@/Composables/Shared/useServerTableState';

/**
 * Crée un mock minimal de `ServerTableState<ContractFilters>` qui n'expose
 * que les méthodes effectivement utilisées par `useContractsTimeScope` ·
 * `filters`, `setFilter`, `patchFilters`. Les autres propriétés sont
 * castées (jamais lues par le composable testé).
 */
function makeTableStateMock(initialFilters: Partial<ContractFilters> = {}) {
    const filters = ref<ContractFilters>({
        vehicleId: null,
        companyId: null,
        driverId: null,
        type: null,
        year: null,
        periodStart: null,
        periodEnd: null,
        ...initialFilters,
    });

    const setFilter = vi.fn(<K extends keyof ContractFilters>(
        key: K,
        value: ContractFilters[K],
    ) => {
        filters.value = { ...filters.value, [key]: value };
    });

    const patchFilters = vi.fn((patch: Partial<ContractFilters>) => {
        filters.value = { ...filters.value, ...patch };
    });

    return {
        filters,
        setFilter,
        patchFilters,
        // Stubs cast (jamais lus)
        search: ref(''),
        sort: ref({ key: null, direction: 'asc' as const }),
        setPage: vi.fn(),
        setPerPage: vi.fn(),
        setSearch: vi.fn(),
        clearFilters: vi.fn(),
    } as unknown as ServerTableState<ContractFilters>;
}

describe('resolveInitialScopeMode', () => {
    it('retourne "year" si query.year est défini', () => {
        const mode = resolveInitialScopeMode({
            year: 2025,
            periodStart: null,
            periodEnd: null,
        });
        expect(mode).toBe('year');
    });

    it('retourne "period" si periodStart présent et year null', () => {
        const mode = resolveInitialScopeMode({
            year: null,
            periodStart: '2025-01-01',
            periodEnd: null,
        });
        expect(mode).toBe('period');
    });

    it('retourne "period" si periodEnd présent et year null', () => {
        const mode = resolveInitialScopeMode({
            year: null,
            periodStart: null,
            periodEnd: '2025-12-31',
        });
        expect(mode).toBe('period');
    });

    it('retourne "year" par défaut quand tout est null', () => {
        const mode = resolveInitialScopeMode({
            year: null,
            periodStart: null,
            periodEnd: null,
        });
        expect(mode).toBe('year');
    });

    it('priorité à year sur period si les deux sont présents', () => {
        // Combinaison anormale (mutuellement exclusifs côté backend) ·
        // par sécurité on choisit `year` qui est le mode dominant.
        const mode = resolveInitialScopeMode({
            year: 2025,
            periodStart: '2025-06-01',
            periodEnd: '2025-06-30',
        });
        expect(mode).toBe('year');
    });
});

describe('useContractsTimeScope', () => {
    const availableYears = computed<readonly number[]>(() => [2024, 2025, 2026]);

    it('initialise scopeMode à "year" quand initialQuery.year est défini', () => {
        const tableState = makeTableStateMock({ year: 2025 });
        const ctx = useContractsTimeScope({
            initialQuery: { year: 2025, periodStart: null, periodEnd: null },
            availableYears,
            tableState,
        });
        expect(ctx.scopeMode.value).toBe('year');
    });

    it('initialise scopeMode à "period" quand initialQuery.period* est présent', () => {
        const tableState = makeTableStateMock({
            periodStart: '2025-06-01',
            periodEnd: '2025-06-30',
        });
        const ctx = useContractsTimeScope({
            initialQuery: {
                year: null,
                periodStart: '2025-06-01',
                periodEnd: '2025-06-30',
            },
            availableYears,
            tableState,
        });
        expect(ctx.scopeMode.value).toBe('period');
    });

    it('defaultYear retourne max(availableYears) si initialQuery.year est null', () => {
        const tableState = makeTableStateMock();
        const ctx = useContractsTimeScope({
            initialQuery: { year: null, periodStart: null, periodEnd: null },
            availableYears,
            tableState,
        });
        expect(ctx.defaultYear.value).toBe(2026);
    });

    it('defaultYear retourne initialQuery.year si défini', () => {
        const tableState = makeTableStateMock({ year: 2024 });
        const ctx = useContractsTimeScope({
            initialQuery: { year: 2024, periodStart: null, periodEnd: null },
            availableYears,
            tableState,
        });
        expect(ctx.defaultYear.value).toBe(2024);
    });

    it('yearOptions reflète availableYears', () => {
        const tableState = makeTableStateMock();
        const ctx = useContractsTimeScope({
            initialQuery: { year: null, periodStart: null, periodEnd: null },
            availableYears,
            tableState,
        });
        expect(ctx.yearOptions.value).toEqual([
            { value: 2024, label: '2024' },
            { value: 2025, label: '2025' },
            { value: 2026, label: '2026' },
        ]);
    });

    it('setScopeMode("period") efface year et auto-ouvre le popover si aucune période saisie', () => {
        const tableState = makeTableStateMock({ year: 2025 });
        const ctx = useContractsTimeScope({
            initialQuery: { year: 2025, periodStart: null, periodEnd: null },
            availableYears,
            tableState,
        });

        ctx.setScopeMode('period');

        expect(ctx.scopeMode.value).toBe('period');
        expect(ctx.periodPopoverOpen.value).toBe(true);
        // patchFilters appelé avec year=null (pas de période préexistante)
        expect(tableState.patchFilters).toHaveBeenCalledWith({
            year: null,
            periodStart: null,
            periodEnd: null,
        });
    });

    it('setScopeMode("year") applique defaultYear et ferme le popover', () => {
        const tableState = makeTableStateMock({
            periodStart: '2025-06-01',
            periodEnd: '2025-06-30',
        });
        const ctx = useContractsTimeScope({
            initialQuery: {
                year: null,
                periodStart: '2025-06-01',
                periodEnd: '2025-06-30',
            },
            availableYears,
            tableState,
        });
        ctx.periodPopoverOpen.value = true;

        ctx.setScopeMode('year');

        expect(ctx.scopeMode.value).toBe('year');
        expect(ctx.periodPopoverOpen.value).toBe(false);
        // patchFilters appelé avec year=defaultYear, period clearé
        expect(tableState.patchFilters).toHaveBeenCalledWith({
            year: 2026,
            periodStart: null,
            periodEnd: null,
        });
    });

    it('setScopeMode("period") préserve la période saisie si déjà présente', () => {
        const tableState = makeTableStateMock({
            year: 2025,
            periodStart: '2025-06-01',
            periodEnd: '2025-06-30',
        });
        const ctx = useContractsTimeScope({
            initialQuery: { year: 2025, periodStart: null, periodEnd: null },
            availableYears,
            tableState,
        });

        ctx.setScopeMode('period');

        // Pas de patchFilters atomique (period déjà présente) ·
        // setFilter('year', null) seul.
        expect(tableState.setFilter).toHaveBeenCalledWith('year', null);
    });

    it('periodLabel formate la plage en français', () => {
        const tableState = makeTableStateMock({
            periodStart: '2025-06-01',
            periodEnd: '2025-06-30',
        });
        const ctx = useContractsTimeScope({
            initialQuery: {
                year: null,
                periodStart: '2025-06-01',
                periodEnd: '2025-06-30',
            },
            availableYears,
            tableState,
        });
        expect(ctx.periodLabel.value).toBe('01/06/2025 → 30/06/2025');
    });

    it('periodLabel retourne le placeholder si aucune période', () => {
        const tableState = makeTableStateMock();
        const ctx = useContractsTimeScope({
            initialQuery: { year: null, periodStart: null, periodEnd: null },
            availableYears,
            tableState,
        });
        expect(ctx.periodLabel.value).toBe('Aucune période sélectionnée');
    });

    it('pickerYear utilise l\'année de periodStart si présent, sinon defaultYear', () => {
        const tableStateA = makeTableStateMock({ periodStart: '2024-03-15' });
        const ctxA = useContractsTimeScope({
            initialQuery: {
                year: null,
                periodStart: '2024-03-15',
                periodEnd: null,
            },
            availableYears,
            tableState: tableStateA,
        });
        expect(ctxA.pickerYear.value).toBe(2024);

        const tableStateB = makeTableStateMock();
        const ctxB = useContractsTimeScope({
            initialQuery: { year: null, periodStart: null, periodEnd: null },
            availableYears,
            tableState: tableStateB,
        });
        expect(ctxB.pickerYear.value).toBe(2026); // defaultYear = max(years)
    });

    it('yearModel.set bascule via patchFilters atomique (year + clear period)', () => {
        const tableState = makeTableStateMock({
            year: 2025,
            periodStart: '2025-06-01',
            periodEnd: '2025-06-30',
        });
        const ctx = useContractsTimeScope({
            initialQuery: { year: 2025, periodStart: null, periodEnd: null },
            availableYears,
            tableState,
        });

        ctx.yearModel.value = 2024;

        expect(tableState.patchFilters).toHaveBeenCalledWith({
            year: 2024,
            periodStart: null,
            periodEnd: null,
        });
    });

    it('periodRange.set bascule via patchFilters atomique (period + clear year)', () => {
        const tableState = makeTableStateMock({ year: 2025 });
        const ctx = useContractsTimeScope({
            initialQuery: { year: 2025, periodStart: null, periodEnd: null },
            availableYears,
            tableState,
        });

        ctx.periodRange.value = {
            startDate: '2025-07-01',
            endDate: '2025-07-31',
        };

        expect(tableState.patchFilters).toHaveBeenCalledWith({
            year: null,
            periodStart: '2025-07-01',
            periodEnd: '2025-07-31',
        });
    });
});
