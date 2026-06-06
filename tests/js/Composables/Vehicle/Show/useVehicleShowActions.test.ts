import { describe, expect, it } from 'vitest';
import { nextTick, ref } from 'vue';
import { useVehicleShowActions } from '@/Composables/Vehicle/Show/useVehicleShowActions';

describe('useVehicleShowActions', () => {
    it('ferme les deux modales quand isExited bascule (anti switch de modale)', async () => {
        const isExited = ref(false);
        const { exitModalOpen, reactivateModalOpen, openExit, openReactivate } =
            useVehicleShowActions(() => isExited.value);

        // L'utilisateur ouvre la modale de retrait et valide la sortie.
        openExit();
        expect(exitModalOpen.value).toBe(true);

        isExited.value = true; // le véhicule passe "retiré"
        await nextTick();

        // La modale de retrait ne doit pas resurgir sur la modale de
        // réactivation qui se monte à sa place.
        expect(exitModalOpen.value).toBe(false);
        expect(reactivateModalOpen.value).toBe(false);

        // Sens inverse : réactivation validée.
        openReactivate();
        expect(reactivateModalOpen.value).toBe(true);

        isExited.value = false;
        await nextTick();

        expect(reactivateModalOpen.value).toBe(false);
        expect(exitModalOpen.value).toBe(false);
    });
});
