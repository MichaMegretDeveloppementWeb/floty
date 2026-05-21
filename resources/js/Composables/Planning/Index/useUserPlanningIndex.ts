import { router } from '@inertiajs/vue3';
import { useWeekDetail } from '@/Composables/Planning/useWeekDetail';

/**
 * Logic for the planning heatmap page:
 *  - delegates the weekly drawer to `useWeekDetail`
 *  - wraps the post-contract-creation handler (closes the drawer + partially reloads `vehicles`
 *    to recompute densities and annual taxes).
 *
 * `preserveScroll: true` on the reload keeps the user anchored on the row they just edited.
 * Without it Inertia would reset window scroll to the top, which is disorienting on long
 * heatmaps where the user already scrolled down to find their vehicle.
 */
export function useUserPlanningIndex(): {
    week: ReturnType<typeof useWeekDetail>;
    onContractsCreated: () => void;
} {
    const week = useWeekDetail();

    const onContractsCreated = (): void => {
        week.close();
        router.reload({ only: ['vehicles'], preserveScroll: true });
    };

    return { week, onContractsCreated };
}
