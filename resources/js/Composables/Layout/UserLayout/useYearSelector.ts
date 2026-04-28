import { computed } from 'vue';
import type { ComputedRef, Ref } from 'vue';

/**
 * Logique des boutons précédent/suivant du sélecteur d'année :
 * borne `[min, max]`, computed de désactivation, handlers.
 *
 * Le composable accepte le `Ref<number>` de l'année (defineModel)
 * pour pouvoir le muter directement depuis `prev`/`next`.
 */
export function useYearSelector(
    year: Ref<number>,
    bounds: { min: number; max: number },
): {
    canPrev: ComputedRef<boolean>;
    canNext: ComputedRef<boolean>;
    prev: () => void;
    next: () => void;
} {
    const canPrev = computed<boolean>(() => year.value > bounds.min);
    const canNext = computed<boolean>(() => year.value < bounds.max);

    const prev = (): void => {
        if (canPrev.value) {
            year.value = year.value - 1;
        }
    };

    const next = (): void => {
        if (canNext.value) {
            year.value = year.value + 1;
        }
    };

    return { canPrev, canNext, prev, next };
}
