import { router } from '@inertiajs/vue3';
import { useWeekDetail } from '@/Composables/Planning/useWeekDetail';

/**
 * Logique de la page « Vue d'ensemble » (heatmap planning) :
 * - délègue à `useWeekDetail` la gestion du drawer hebdo
 * - encapsule le handler post-création de contrats (ferme le drawer
 *   + reload partiel des vehicles pour recalculer densités et taxes
 *   annuelles).
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
