import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { closeOnSuccess } from '@/Composables/Shared/inertiaModalCallbacks';
import {
    computeVfcUpdateImpact,
    findStrictlyContainingVfc,
    hasDestructiveImpact,
} from '@/Composables/Vehicle/Show/computeVfcUpdateImpact';
import type { VfcImpact } from '@/Composables/Vehicle/Show/computeVfcUpdateImpact';
import type { VfcEditFormShape } from '@/pages/User/Vehicles/Show/forms';
import { store as vfcStoreRoute } from '@/routes/user/vehicle-fiscal-characteristics';

type Vfc = App.Data.User.Vehicle.VehicleFiscalCharacteristicsData;
type ChangeReason = App.Enums.Vehicle.FiscalCharacteristicsChangeReason;
type SelectOption = { value: string; label: string };

/**
 * Inertia form + UI state for the VFC create modal opened from the History modal
 * (counterpart of {@link useVfcEditForm} for edit).
 *
 *   - Pre-fills the form with the current VFC when `props.current` is provided (typical case:
 *     only one field changes, e.g. recomputed CO₂). Range fields (`effective_from`, `effective_to`)
 *     stay empty to force an explicit date choice. If `props.current` is null
 *     (vehicle without an active VFC), falls back to default values.
 *   - Exposes selectable reasons (all except `initial_creation`, which is reserved for the system
 *     and applied by CreateVehicleAction at first VFC creation).
 *   - Live-computes the impact on neighbours via `computeVfcUpdateImpact()` with `editingId = null`
 *     (no exclusion: simulating a new range inserted into the full history).
 *   - Dispatches POST submit and closes the modal on success.
 */
export function useVfcCreateForm(
    props: { history: ReadonlyArray<Vfc>; vehicleId: number; current: Vfc | null },
    open: Ref<boolean>,
): {
    form: InertiaForm<VfcEditFormShape & { confirmed: boolean }>;
    changeReasonOptions: SelectOption[];
    isOtherChange: ComputedRef<boolean>;
    canSubmit: ComputedRef<boolean>;
    impacts: ComputedRef<VfcImpact[]>;
    nonDestructiveImpacts: ComputedRef<VfcImpact[]>;
    destructiveImpacts: ComputedRef<VfcImpact[]>;
    isDestructive: ComputedRef<boolean>;
    blockingContainerVfc: ComputedRef<Vfc | null>;
    confirmationOpen: Ref<boolean>;
    requestSubmit: () => void;
    confirmSubmit: () => void;
} {
    // Build the initial state from the current VFC if provided: the user only adjusts the changing
    // fields and picks the effective date. Range fields stay empty to force an explicit date choice
    // (otherwise we would create an identical range, refused by the backend).
    const buildInitialState = (): VfcEditFormShape & { confirmed: boolean } => {
        const current = props.current;

        if (current === null) {
            return {
                reception_category: 'M1',
                vehicle_user_type: 'VP',
                body_type: 'CI',
                seats_count: 5,
                energy_source: 'gasoline',
                underlying_combustion_engine_type: '',
                euro_standard: 'euro_6d_isc_fcm',
                homologation_method: 'WLTP',
                co2_wltp: null,
                co2_nedc: null,
                taxable_horsepower: null,
                accepts_e85: false,
                kerb_mass: null,
                handicap_access: false,
                m1_special_use: false,
                n1_passenger_transport: false,
                n1_removable_second_row_seat: false,
                n1_ski_lift_use: false,
                effective_from: '',
                effective_to: '',
                change_reason: 'recharacterization',
                change_note: '',
                confirmed: false,
            };
        }

        return {
            reception_category: current.receptionCategory,
            vehicle_user_type: current.vehicleUserType,
            body_type: current.bodyType,
            seats_count: current.seatsCount,
            energy_source: current.energySource,
            underlying_combustion_engine_type: current.underlyingCombustionEngineType ?? '',
            euro_standard: current.euroStandard ?? '',
            homologation_method: current.homologationMethod,
            co2_wltp: current.co2Wltp,
            co2_nedc: current.co2Nedc,
            taxable_horsepower: current.taxableHorsepower,
            accepts_e85: current.acceptsE85,
            kerb_mass: current.kerbMass,
            handicap_access: current.handicapAccess,
            m1_special_use: current.m1SpecialUse,
            n1_passenger_transport: current.n1PassengerTransport,
            n1_removable_second_row_seat: current.n1RemovableSecondRowSeat,
            n1_ski_lift_use: current.n1SkiLiftUse,
            effective_from: '',
            effective_to: '',
            change_reason: 'recharacterization',
            change_note: '',
            confirmed: false,
        };
    };

    const form = useForm<VfcEditFormShape & { confirmed: boolean }>(buildInitialState());

    const changeReasonOptions: SelectOption[] = [
        { value: 'recharacterization', label: 'Reclassement fiscal' },
        { value: 'regulation_change', label: 'Changement réglementaire' },
        { value: 'other_change', label: 'Autre changement' },
        { value: 'input_correction', label: 'Correction de saisie' },
    ];

    // On each opening, reset the form starting from the current VFC (if provided).
    // UX benefit: closing the modal after edits and reopening shows a fresh pre-filled state, not
    // orphaned values from the previous session.
    watch(open, (isOpen) => {
        if (isOpen) {
            Object.assign(form, buildInitialState());
            form.clearErrors();
        }
    });

    // Anti-ghost-data watchers (same rules as in edit mode). User type tracks the reception
    // category since the DB enforces this 1:1 mapping (M1→VP, N1→VU).
    watch(
        () => form.reception_category,
        (cat) => {
            form.vehicle_user_type = cat === 'N1' ? 'VU' : 'VP';

            if (cat !== 'M1') {
                form.m1_special_use = false;
            }

            if (cat !== 'N1') {
                form.n1_passenger_transport = false;
                form.n1_removable_second_row_seat = false;
                form.n1_ski_lift_use = false;
            }
        },
    );

    watch(
        () => form.body_type,
        (body) => {
            if (body !== 'CTTE') {
                form.n1_passenger_transport = false;
                form.n1_removable_second_row_seat = false;
            }

            if (body !== 'BE') {
                form.n1_ski_lift_use = false;
            }
        },
    );

    const isOtherChange = computed<boolean>(
        () => form.change_reason === 'other_change',
    );

    const blockingContainerVfc = computed<Vfc | null>(() => {
        return findStrictlyContainingVfc(
            props.history,
            null,
            form.effective_from,
            form.effective_to === '' ? null : form.effective_to,
        );
    });

    const canSubmit = computed<boolean>(() => {
        if (form.effective_from === '') {
            return false;
        }

        if (form.change_reason === '') {
            return false;
        }

        if (isOtherChange.value && form.change_note.trim() === '') {
            return false;
        }

        // Backend parity guard: refuse a range strictly contained in an existing VFC. The backend
        // re-catches this, but blocking UI-side avoids a tedious round-trip and shows the contextual
        // message directly in the modal.
        if (blockingContainerVfc.value !== null) {
            return false;
        }

        return true;
    });

    const impacts = computed<VfcImpact[]>(() => {
        if (form.effective_from === '') {
            return [];
        }

        return computeVfcUpdateImpact(
            props.history,
            null,
            form.effective_from,
            form.effective_to === '' ? null : form.effective_to,
        );
    });

    const isDestructive = computed<boolean>(
        () => hasDestructiveImpact(impacts.value),
    );

    const destructiveImpacts = computed<VfcImpact[]>(
        () => impacts.value.filter((i) => i.type === 'delete'),
    );

    const nonDestructiveImpacts = computed<VfcImpact[]>(
        () => impacts.value.filter((i) => i.type !== 'delete'),
    );

    const confirmationOpen = ref<boolean>(false);

    const submit = (confirmed: boolean): void => {
        if (!canSubmit.value) {
            return;
        }

        form.transform((data) => ({
            ...data,
            confirmed,
            change_reason: data.change_reason as ChangeReason,
            change_note: data.change_note === '' ? null : data.change_note,
            effective_to: data.effective_to === '' ? null : data.effective_to,
            euro_standard: data.euro_standard === '' ? null : data.euro_standard,
            underlying_combustion_engine_type:
                data.underlying_combustion_engine_type === ''
                    ? null
                    : data.underlying_combustion_engine_type,
        })).post(
            vfcStoreRoute.url({ vehicle: props.vehicleId }),
            closeOnSuccess(open, confirmationOpen),
        );
    };

    const requestSubmit = (): void => {
        if (!canSubmit.value) {
            return;
        }

        if (isDestructive.value) {
            confirmationOpen.value = true;

            return;
        }

        submit(false);
    };

    const confirmSubmit = (): void => {
        submit(true);
    };

    return {
        form,
        changeReasonOptions,
        isOtherChange,
        canSubmit,
        impacts,
        nonDestructiveImpacts,
        destructiveImpacts,
        isDestructive,
        blockingContainerVfc,
        confirmationOpen,
        requestSubmit,
        confirmSubmit,
    };
}

/**
 * Helper used by the parent partial to open/close the create modal.
 * No reference to a current VFC since this is a creation.
 */
export function useVfcCreateModalState(): {
    open: Ref<boolean>;
    requestCreate: () => void;
} {
    const open = ref<boolean>(false);

    const requestCreate = (): void => {
        open.value = true;
    };

    return { open, requestCreate };
}
