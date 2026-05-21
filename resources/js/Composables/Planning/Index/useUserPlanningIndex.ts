import { router } from '@inertiajs/vue3';
import { useWeekDetail } from '@/Composables/Planning/useWeekDetail';

/**
 * Logic for the planning heatmap page:
 *  - delegates the weekly drawer to `useWeekDetail`
 *  - wraps the post-contract-creation handler (closes the drawer + partially reloads
 *    `vehicles` and the deferred cost groups so densities, monthly rental totals
 *    and yearly taxes refresh in place without a full page visit).
 *
 * Two partial reloads are dispatched in parallel:
 *  - the "fast" payload bundles the slim `vehicles` list, `fullYearCosts` and
 *    `monthlyRentals` so the heatmap colours, yearly tax tooltip and top
 *    monthly rental totals update together as soon as the cheap calculations
 *    are ready;
 *  - the "slow" payload reloads `realCosts` separately so the long-running
 *    real billed tax keeps its own skeleton without blocking the rest.
 *
 * Bundling avoids the 4-parallel-requests pattern that was occasionally
 * tripping the global rate limiter when the user created several contracts
 * in quick succession.
 *
 * `preserveScroll: true` on every reload keeps the user anchored on the row
 * they just edited; without it Inertia would reset window scroll to the top,
 * which is disorienting on long heatmaps.
 */
export function useUserPlanningIndex(): {
    week: ReturnType<typeof useWeekDetail>;
    onContractsCreated: () => void;
} {
    const week = useWeekDetail();

    const onContractsCreated = (): void => {
        week.close();
        router.reload({
            only: ['vehicles', 'fullYearCosts', 'monthlyRentals'],
            preserveScroll: true,
        });
        router.reload({ only: ['realCosts'], preserveScroll: true });
    };

    return { week, onContractsCreated };
}
