import type { Ref } from 'vue';

/**
 * Helper Inertia callbacks pour les modaux · ferme une ou plusieurs
 * `Ref<boolean>` au `onSuccess` du visit. Utilisé par les forms Vehicle/Show
 * (et au-delà si besoin) pour éviter de répéter le pattern `preserveScroll
 * + onSuccess close`.
 *
 * Usage simple ·
 *   form.post(url, closeOnSuccess(open));
 *
 * Usage à plusieurs refs (modale principale + sous-modale de
 * confirmation) ·
 *   form.post(url, closeOnSuccess(open, confirmationOpen));
 *
 * Le retour est typé exactement comme l'objet d'options Inertia attendu,
 * donc on peut l'étendre via spread si besoin local ·
 *   form.post(url, { ...closeOnSuccess(open), onError: () => ... });
 */
export function closeOnSuccess(...refs: Ref<boolean>[]): {
    preserveScroll: true;
    onSuccess: () => void;
} {
    return {
        preserveScroll: true,
        onSuccess: () => {
            for (const r of refs) {
                r.value = false;
            }
        },
    };
}
