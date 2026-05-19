import { ref } from 'vue';
import type { Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import { week as planningWeekRoute } from '@/routes/user/planning';

/**
 * Loads a week's detail (planning drawer) on demand.
 *
 * Wraps the `GET /app/planning/week` call and the drawer open state. The drawer opens automatically
 * on successful fetch (`loadingWeek` flips to false before opening to avoid a flash).
 *
 * Planning/Index.vue only instantiates this composable and wires Heatmap/WeekDrawer events onto it.
 */
export type UseWeekDetailReturn = {
    drawerOpen: Ref<boolean>;
    weekData: Ref<App.Data.User.Planning.PlanningWeekData | null>;
    loading: Ref<boolean>;
    /**
     * Loads the week and opens the drawer on success.
     *
     * `year` is required; without it the controller falls back to `currentYear()` and the drawer
     * always opens on the current calendar year instead of the user's selected year.
     *
     * `companyId`: when provided, enables backend company-locked mode (anonymisation of other
     * companies' contracts + filtering `companiesOnWeek`). Used by the Company view to avoid
     * revealing which other companies occupy the vehicles.
     */
    open: (vehicleId: number, week: number, year: number, companyId?: number) => Promise<void>;
    /** Closes the drawer (keeps weekData to avoid a flash). */
    close: () => void;
};

export function useWeekDetail(): UseWeekDetailReturn {
    const api = useApi();
    const drawerOpen = ref(false);
    const weekData = ref<App.Data.User.Planning.PlanningWeekData | null>(null);
    const loading = ref(false);

    const open = async (
        vehicleId: number,
        week: number,
        year: number,
        companyId?: number,
    ): Promise<void> => {
        loading.value = true;

        try {
            const params: Record<string, number> = { vehicleId, week, year };

            if (companyId !== undefined) {
                params.companyId = companyId;
            }

            weekData.value = await api.get<App.Data.User.Planning.PlanningWeekData>(
                planningWeekRoute.url(),
                params,
            );
            drawerOpen.value = true;
        } catch {
            // Error toast already shown by useApi.
        } finally {
            loading.value = false;
        }
    };

    const close = (): void => {
        drawerOpen.value = false;
    };

    return { drawerOpen, weekData, loading, open, close };
}
