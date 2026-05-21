import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { closeOnSuccess } from '@/Composables/Shared/inertiaModalCallbacks';
import {
    computeVfcUpdateImpact,
    hasDestructiveImpact,
} from '@/Composables/Vehicle/Show/computeVfcUpdateImpact';
import type { VfcImpact } from '@/Composables/Vehicle/Show/computeVfcUpdateImpact';
import type { VfcEditFormShape } from '@/pages/User/Vehicles/Show/forms';
import { update as vfcUpdateRoute } from '@/routes/user/vehicle-fiscal-characteristics';

type Vfc = App.Data.User.Vehicle.VehicleFiscalCharacteristicsData;
type ChangeReason = App.Enums.Vehicle.FiscalCharacteristicsChangeReason;
type SelectOption = { value: string; label: string };

/**
 * Inertia form + UI state for the edit modal of an isolated VFC.
 *
 *   - Synchronises `useForm` when `props.editing` changes (the modal is shared across all history rows).
 *   - Exposes selectable reasons (all except `initial_creation`, reserved for the system).
 *     History allows more reasons than the vehicle Edit flow.
 *   - Live-computes the impact on neighbours via `computeVfcUpdateImpact()` so the modal can preview
 *     adjustments and trigger a destructive confirmation before submit (mirror of the backend cascade).
 *   - Dispatches PATCH submit and closes the modal on success.
 */
export function useVfcEditForm(
    props: { editing: Vfc | null; history: ReadonlyArray<Vfc> },
    open: Ref<boolean>,
): {
    form: InertiaForm<VfcEditFormShape & { confirmed: boolean }>;
    changeReasonOptions: SelectOption[];
    isOtherChange: ComputedRef<boolean>;
    isInitialCreation: ComputedRef<boolean>;
    canSubmit: ComputedRef<boolean>;
    impacts: ComputedRef<VfcImpact[]>;
    nonDestructiveImpacts: ComputedRef<VfcImpact[]>;
    destructiveImpacts: ComputedRef<VfcImpact[]>;
    isDestructive: ComputedRef<boolean>;
    confirmationOpen: Ref<boolean>;
    requestSubmit: () => void;
    confirmSubmit: () => void;
} {
    const form = useForm<VfcEditFormShape & { confirmed: boolean }>({
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
    });

    const changeReasonOptions: SelectOption[] = [
        { value: 'recharacterization', label: 'Reclassement fiscal' },
        { value: 'regulation_change', label: 'Changement réglementaire' },
        { value: 'other_change', label: 'Autre changement' },
        { value: 'input_correction', label: 'Correction de saisie' },
    ];

    watch(
        () => props.editing,
        (value) => {
            if (value) {
                form.reception_category = value.receptionCategory;
                form.vehicle_user_type = value.vehicleUserType;
                form.body_type = value.bodyType;
                form.seats_count = value.seatsCount;
                form.energy_source = value.energySource;
                form.underlying_combustion_engine_type =
                    value.underlyingCombustionEngineType ?? '';
                form.euro_standard = value.euroStandard ?? '';
                form.homologation_method = value.homologationMethod;
                form.co2_wltp = value.co2Wltp;
                form.co2_nedc = value.co2Nedc;
                form.taxable_horsepower = value.taxableHorsepower;
                form.accepts_e85 = value.acceptsE85;
                form.kerb_mass = value.kerbMass;
                form.handicap_access = value.handicapAccess;
                form.m1_special_use = value.m1SpecialUse;
                form.n1_passenger_transport = value.n1PassengerTransport;
                form.n1_removable_second_row_seat = value.n1RemovableSecondRowSeat;
                form.n1_ski_lift_use = value.n1SkiLiftUse;
                form.effective_from = value.effectiveFrom;
                form.effective_to = value.effectiveTo ?? '';
                // For an "Initial creation" we keep the original value as-is.
                // The Reason field is hidden in the UI and not editable: semantically it is not
                // a change, it is the origin.
                form.change_reason = value.changeReason;
                form.change_note = value.changeNote ?? '';
                form.confirmed = false;
            }

            form.clearErrors();
        },
    );

    // Anti-ghost-data watchers: switching category or body resets M1/N1 flags tied to the previous
    // combination to avoid persisting an inconsistent state (e.g. M1 + n1_ski_lift_use=true). User
    // type also tracks the reception category (DB enforces M1→VP, N1→VU).
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

    const isInitialCreation = computed<boolean>(
        () => form.change_reason === 'initial_creation',
    );

    const canSubmit = computed<boolean>(() => {
        if (form.effective_from === '') {
            return false;
        }

        if (form.change_reason === '') {
            return false;
        }

        // initial_creation has no UI-editable reason; the value is preserved as-is, so canSubmit
        // does not depend on change_note in this case.
        if (
            !isInitialCreation.value
            && isOtherChange.value
            && form.change_note.trim() === ''
        ) {
            return false;
        }

        return true;
    });

    const impacts = computed<VfcImpact[]>(() => {
        if (!props.editing || form.effective_from === '') {
            return [];
        }

        return computeVfcUpdateImpact(
            props.history,
            props.editing.id,
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
        if (!canSubmit.value || !props.editing) {
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
        })).patch(
            vfcUpdateRoute.url({ vehicleFiscalCharacteristic: props.editing.id }),
            closeOnSuccess(open, confirmationOpen),
        );
    };

    const requestSubmit = (): void => {
        if (!canSubmit.value || !props.editing) {
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
        isInitialCreation,
        canSubmit,
        impacts,
        nonDestructiveImpacts,
        destructiveImpacts,
        isDestructive,
        confirmationOpen,
        requestSubmit,
        confirmSubmit,
    };
}

/**
 * Helper used by the partial to open/close the modal and track the VFC being edited.
 * Keeps the open logic out of the modal's own `<script setup>`.
 */
export function useVfcEditModalState(): {
    open: Ref<boolean>;
    editing: Ref<Vfc | null>;
    requestEdit: (vfc: Vfc) => void;
} {
    const open = ref<boolean>(false);
    const editing = ref<Vfc | null>(null);

    const requestEdit = (vfc: Vfc): void => {
        editing.value = vfc;
        open.value = true;
    };

    return { open, editing, requestEdit };
}
