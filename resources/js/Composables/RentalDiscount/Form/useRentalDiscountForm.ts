/**
 * Form composable pour Create/Edit RentalDiscount (Lot 4 du chantier).
 *
 * Encapsule ·
 *  - `useForm` Inertia (state + submit + errors)
 *  - conversion pourcentage UI ↔ basis points DB (1 050 bp = 10,50 %)
 *  - check chevauchement live debounced via POST /check-conflicts
 *
 * **Convention snake_case** · les clés du `useForm` sont en snake_case
 * pour matcher l'attente backend (Spatie Data + `SnakeCaseMapper`).
 * Sans cette convention, le serveur ne trouve pas les champs en entrée
 * (validation `required` échoue) ET les `form.errors` retournés sont
 * en snake_case donc les `<InputError :message="form.errors.start_date" />`
 * ne se résolvent pas si on bind en camelCase. Convention héritée du
 * pattern `useDriverForm`, `useContractForm`, etc.
 */

import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import type { DateRange } from '@/Composables/Ui/DateRangePicker/useDateRangePicker';
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
    company_id: number | null;
    start_date: string;
    end_date: string;
    discount_basis_points: number;
    label: string | null;
    notes: string | null;
    vehicle_ids: number[];
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
    range: Ref<DateRange>;
    /** Toggle « date de fin indéfinie » exigé par le DateRangePicker · toujours `false` ici (une réduction commerciale a forcément une date de fin). */
    ongoing: Ref<boolean>;
    /** Année initiale d'ouverture du DateRangePicker (centre sur start_date existante ou année courante). */
    pickerInitialYear: number;
    /** Mois initial d'ouverture du DateRangePicker (1..12, centre sur start_date existante ou mois courant). */
    pickerInitialMonth: number;
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

    // `DateRangePicker` consomme `range` (v-model) et `ongoing` (v-model).
    // `ongoing` reste `false` constant · une réduction commerciale a
    // toujours une date de fin (contrairement aux indispos).
    const range = ref<DateRange>({
        startDate: initial.startDate !== '' ? initial.startDate : null,
        endDate: initial.endDate !== '' ? initial.endDate : null,
    });
    const ongoing = ref<boolean>(false);

    // Année/mois d'ouverture du calendrier · centre sur la date existante
    // (Edit) ou la date courante (Create). Stable après init · le
    // DateRangePicker maintient son propre state interne ensuite.
    const today = new Date();
    const startSource = initial.startDate !== '' ? new Date(initial.startDate) : today;
    const pickerInitialYear = startSource.getFullYear();
    const pickerInitialMonth = startSource.getMonth() + 1;

    // Form Inertia · clés snake_case (cf. doc en tête).
    const form = useForm<RentalDiscountFormPayload>({
        company_id: initial.companyId,
        start_date: initial.startDate,
        end_date: initial.endDate,
        discount_basis_points: Math.round(initial.discountPercent * 100),
        label: initial.label,
        notes: initial.notes,
        vehicle_ids: initial.vehicleIds,
    });

    // Watcher de synchro pourcentage UI → bp form payload.
    watch(discountPercent, (next) => {
        form.discount_basis_points = Math.round(next * 100);
    });

    // Sync `range` (sélection calendrier) → form payload (snake_case).
    watch(range, (next) => {
        form.start_date = next.startDate ?? '';
        form.end_date = next.endDate ?? '';
    }, { deep: true });

    // Watcher du toggle « tous les véhicules ». Quand on coche, on vide
    // la liste. Quand on décoche, on laisse l'utilisateur sélectionner.
    watch(appliesToAllVehicles, (next) => {
        if (next) {
            form.vehicle_ids = [];
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
            form.company_id === null
            || form.start_date === ''
            || form.end_date === ''
            || form.start_date > form.end_date
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
                    company_id: form.company_id,
                    start_date: form.start_date,
                    end_date: form.end_date,
                    vehicle_ids: appliesToAllVehicles.value ? [] : form.vehicle_ids,
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
            () => form.company_id,
            () => form.start_date,
            () => form.end_date,
            () => form.vehicle_ids,
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
        if (form.company_id === null) {
            return false;
        }
        if (form.start_date === '' || form.end_date === '') {
            return false;
        }
        if (form.start_date > form.end_date) {
            return false;
        }
        if (form.discount_basis_points < 1 || form.discount_basis_points > 10_000) {
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
        range,
        ongoing,
        pickerInitialYear,
        pickerInitialMonth,
        conflicts,
        isCheckingConflicts,
        hasConflicts,
        canSubmit,
        submit,
    };
}
