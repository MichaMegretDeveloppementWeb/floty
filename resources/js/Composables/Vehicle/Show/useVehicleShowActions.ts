import { ref } from 'vue';
import type { Ref } from 'vue';

/**
 * État UI des modales d'actions disponibles sur la page Show d'un
 * véhicule (Sortie de flotte / Réactivation). Pas de logique métier
 * (chaque modale embarque son propre composable de form Inertia) —
 * ici on ne gère que l'open/close.
 *
 * Découpage dans un composable séparé pour garder VehicleHeader.vue
 * minimal (cf. règle « strict minimum dans les .vue »).
 */
export function useVehicleShowActions(): {
    exitModalOpen: Ref<boolean>;
    reactivateModalOpen: Ref<boolean>;
    openExit: () => void;
    openReactivate: () => void;
} {
    const exitModalOpen = ref<boolean>(false);
    const reactivateModalOpen = ref<boolean>(false);

    const openExit = (): void => {
        exitModalOpen.value = true;
    };

    const openReactivate = (): void => {
        reactivateModalOpen.value = true;
    };

    return {
        exitModalOpen,
        reactivateModalOpen,
        openExit,
        openReactivate,
    };
}
