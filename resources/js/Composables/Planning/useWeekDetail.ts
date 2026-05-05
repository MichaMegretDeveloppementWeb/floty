import { ref } from 'vue';
import type { Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import { week as planningWeekRoute } from '@/routes/user/planning';

/**
 * Charge le détail d'une semaine (drawer planning) à la demande.
 *
 * Encapsule l'appel `GET /app/planning/week` + l'état d'ouverture du
 * drawer. Le drawer s'ouvre automatiquement à la fin du fetch réussi
 * (loadingWeek passe à false avant l'ouverture pour éviter le flash).
 *
 * Conforme R7 : aucune logique réactive ne reste dans la page -
 * Planning/Index.vue ne fait qu'instancier ce composable et brancher
 * les events Heatmap/WeekDrawer dessus.
 */
export type UseWeekDetailReturn = {
    drawerOpen: Ref<boolean>;
    weekData: Ref<App.Data.User.Planning.PlanningWeekData | null>;
    loading: Ref<boolean>;
    /**
     * Charge la semaine et ouvre le drawer en cas de succès.
     *
     * `year` est requis depuis chantier η Phase 5 — sans lui le
     * controller fallback sur `currentYear()` et le drawer ouvre
     * toujours sur l'année calendaire courante au lieu de l'année
     * sélectionnée par l'utilisateur dans le sélecteur top-right.
     */
    open: (vehicleId: number, week: number, year: number) => Promise<void>;
    /** Ferme le drawer (sans réinitialiser weekData pour éviter le flash). */
    close: () => void;
};

export function useWeekDetail(): UseWeekDetailReturn {
    const api = useApi();
    const drawerOpen = ref(false);
    const weekData = ref<App.Data.User.Planning.PlanningWeekData | null>(null);
    const loading = ref(false);

    const open = async (vehicleId: number, week: number, year: number): Promise<void> => {
        loading.value = true;

        try {
            weekData.value = await api.get<App.Data.User.Planning.PlanningWeekData>(
                planningWeekRoute.url(),
                { vehicleId, week, year },
            );
            drawerOpen.value = true;
        } catch {
            // Toast erreur déjà affiché par useApi
        } finally {
            loading.value = false;
        }
    };

    const close = (): void => {
        drawerOpen.value = false;
    };

    return { drawerOpen, weekData, loading, open, close };
}
