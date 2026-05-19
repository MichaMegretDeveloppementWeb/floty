import { router } from '@inertiajs/vue3';
import { useWeekDetail } from '@/Composables/Planning/useWeekDetail';

/**
 * Logic for the planning heatmap page:
 *  - delegates the weekly drawer to `useWeekDetail`
 *  - wraps the post-contract-creation handler (closes the drawer + partially reloads `vehicles`
 *    to recompute densities and annual taxes).
 */
export function useUserPlanningIndex(): {
    week: ReturnType<typeof useWeekDetail>;
    onContractsCreated: () => void;
} {
    const week = useWeekDetail();

    const onContractsCreated = (): void => {
        week.close();
        router.reload({ only: ['vehicles'] });
    };

    return { week, onContractsCreated };
}
