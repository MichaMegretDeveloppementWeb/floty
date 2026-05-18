/**
 * Form composable pour Create/Edit RentalDiscount (Lot 4 du chantier).
 *
 * Encapsule ·
 *  - `useForm` Inertia (state + submit + errors)
 *  - conversion pourcentage UI ↔ basis points DB (1 050 bp = 10,50 %)
 *  - check chevauchement live debounced via POST /check-conflicts
 *
 * Le form ne se soumet pas si un conflit est détecté côté UI · la
 * validation backend re-check de toute façon (defense in depth).
 */

import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { checkConflicts as checkConflictsRoute } from '@/routes/user/rental-discounts';

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]!) : '';
}

export type ConflictItem = {
    id: number;
    label: string | null;
    startDate: string;
    endDate: string;
    discountBasisPoints: number;
    vehiclesCount: number;
    isAllVehicles: boolean;
};

export type RentalDiscountFormPayload = {
    companyId: number | null;
    startDate: string;
    endDate: string;
    discountBasisPoints: number;
    label: string | null;
    notes: string | null;
    vehicleIds: number[];
};

export type RentalDiscountFormInitial = {
    /** Pré-rempli en Edit · null en Create. */
    id: number | null;
    /** Pré-rempli en Edit · companyId du parent. Null en Create. */
    companyId: number | null;
    startDate: string;
    endDate: string;
    /** Pourcentage UI (0..100). Converti en bp à submit. */
    discountPercent: number;
    label: string | null;
    notes: string | null;
    vehicleIds: number[];
};

export function useRentalDiscountForm(
    initial: RentalDiscountFormInitial,
    onSubmit: (form: ReturnType<typeof useForm>) => void,
): {
    form: ReturnType<typeof useForm>;
    discountPercent: Ref<number>;
    appliesToAllVehicles: Ref<boolean>;
    conflicts: Ref<ConflictItem[]>;
    isCheckingConflicts: Ref<boolean>;
    hasConflicts: ComputedRef<boolean>;
    canSubmit: ComputedRef<boolean>;
    submit: () => void;
} {
    // L'utilisateur saisit en %, on convertit en bp à la soumission.
    const discountPercent = ref<number>(initial.discountPercent);

    // Toggle "S'applique à tous les véhicules de l'entreprise".
    // `true` = liste vide (sémantique « tous »). Initialisé selon
    // l'état d'origine (vehicleIds vide).
    const appliesToAllVehicles = ref<boolean>(initial.vehicleIds.length === 0);

    const form = useForm<RentalDiscountFormPayload>({
        companyId: initial.companyId,
        startDate: initial.startDate,
        endDate: initial.endDate,
        discountBasisPoints: Math.round(initial.discountPercent * 100),
        label: initial.label,
        notes: initial.notes,
        vehicleIds: initial.vehicleIds,
    });

    // Watcher de synchro pourcentage UI → bp form payload.
    watch(discountPercent, (next) => {
        form.discountBasisPoints = Math.round(next * 100);
    });

    // Watcher du toggle « tous les véhicules ». Quand on coche, on vide
    // la liste. Quand on décoche, on laisse l'utilisateur sélectionner.
    watch(appliesToAllVehicles, (next) => {
        if (next) {
            form.vehicleIds = [];
        }
    });

    // Check chevauchement debounced 400 ms.
    const conflicts = ref<ConflictItem[]>([]);
    const isCheckingConflicts = ref<boolean>(false);
    let debounceTimer: ReturnType<typeof setTimeout> | null = null;

    function debouncedCheckConflicts(): void {
        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(() => {
            void runCheckConflicts();
        }, 400);
    }

    async function runCheckConflicts(): Promise<void> {
        if (
            form.companyId === null
            || form.startDate === ''
            || form.endDate === ''
            || form.startDate > form.endDate
        ) {
            conflicts.value = [];
            return;
        }

        isCheckingConflicts.value = true;
        try {
            // Fetch direct pour bypasser le toast erreur auto de useApi ·
            // un échec silencieux est acceptable ici, le submit backend
            // re-check de toute façon (defense in depth).
            const response = await fetch(checkConflictsRoute.url(), {
                method: 'POST',
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                body: JSON.stringify({
                    company_id: form.companyId,
                    start_date: form.startDate,
                    end_date: form.endDate,
                    vehicle_ids: appliesToAllVehicles.value ? [] : form.vehicleIds,
                    exclude_id: initial.id,
                }),
            });
            if (!response.ok) {
                return;
            }
            const body = (await response.json()) as {
                hasConflicts: boolean;
                conflicts: ConflictItem[];
            };
            conflicts.value = body.conflicts;
        } catch {
            // Erreur réseau · on n'écrase pas la liste précédente.
        } finally {
            isCheckingConflicts.value = false;
        }
    }

    // Trigger le check à chaque modif des champs pertinents.
    watch(
        [
            () => form.companyId,
            () => form.startDate,
            () => form.endDate,
            () => form.vehicleIds,
            appliesToAllVehicles,
        ],
        () => debouncedCheckConflicts(),
        { deep: true },
    );

    const hasConflicts = computed<boolean>(() => conflicts.value.length > 0);

    const canSubmit = computed<boolean>(() => {
        if (form.processing) {
            return false;
        }
        if (hasConflicts.value) {
            return false;
        }
        if (form.companyId === null) {
            return false;
        }
        if (form.startDate === '' || form.endDate === '') {
            return false;
        }
        if (form.startDate > form.endDate) {
            return false;
        }
        if (form.discountBasisPoints < 1 || form.discountBasisPoints > 10_000) {
            return false;
        }
        return true;
    });

    function submit(): void {
        onSubmit(form);
    }

    return {
        form,
        discountPercent,
        appliesToAllVehicles,
        conflicts,
        isCheckingConflicts,
        hasConflicts,
        canSubmit,
        submit,
    };
}
