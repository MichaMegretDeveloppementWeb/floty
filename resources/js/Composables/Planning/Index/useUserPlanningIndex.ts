import { router } from '@inertiajs/vue3';
import { useWeekDetail } from '@/Composables/Planning/useWeekDetail';

/**
 * Logique de la page « Vue d'ensemble » (heatmap planning) :
 * - délègue à `useWeekDetail` la gestion du drawer hebdo
 * - encapsule le handler post-création d'attributions (ferme le
 *   drawer + reload partiel des vehicles pour recalculer densités
 *   et taxes annuelles).
 */
export function useUserPlanningIndex(): {
    week: ReturnType<typeof useWeekDetail>;
    onAssignmentsCreated: () => void;
} {
    const week = useWeekDetail();

    const onAssignmentsCreated = (): void => {
        week.close();
        router.reload({ only: ['vehicles'] });
    };

    return { week, onAssignmentsCreated };
}
