import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import type { VehicleEditFormShape } from '@/pages/User/Vehicles/Edit/forms';
import { update as vehiclesUpdateRoute } from '@/routes/user/vehicles';

type Vehicle = App.Data.User.Vehicle.VehicleData;
type Fiscal = App.Data.User.Vehicle.VehicleFiscalCharacteristicsData;
type ChangeReason = App.Enums.Vehicle.FiscalCharacteristicsChangeReason;
type SelectOption = { value: string; label: string };

/**
 * Inertia form + UI state for the vehicle Edit page.
 *
 * Identity fields (plate, brand, dates, mileage, notes) are freely editable.
 *
 * Fiscal change handling depends on `form.create_new_version`:
 *   - default `false`: changes update the current VFC in place, retroactive on
 *     its whole effective period. Suited to typo corrections after vehicle
 *     creation; no metadata required.
 *   - `true`: changes insert a new VFC row. `effective_from` + `change_reason`
 *     become mandatory; the retroactive cascade (Delete future versions when
 *     `effective_from` falls before them) is confirmed via a modal.
 *
 * The opt-in toggle is materialised by a checkbox in the Edit page; the
 * metadata section only renders when both `hasFiscalChanges` and
 * `create_new_version` are true.
 *
 * Composable behaviour:
 *   - pre-fills `useForm` from `props.vehicle` + its current VFC,
 *   - exposes selectable reasons (`changeReasonOptions`, the 3 `userSelectableForNewVersion` reasons),
 *   - computes `hasFiscalChanges` (any fiscal field modified), `requiresVersionMetadata`
 *     (true only when fiscal changed and the new-version checkbox is on), and `isOtherChange`,
 *   - computes the list of historical versions that will be deleted (`versionsToBeDeleted`) when
 *     the user opted into a new version and chose a date that falls before them,
 *   - exposes `requestSubmit()` which opens the ConfirmModal if the cascade applies, else submits directly.
 */
export function useVehicleEditForm(props: { vehicle: Vehicle }): {
    form: InertiaForm<VehicleEditFormShape>;
    changeReasonOptions: SelectOption[];
    isOtherChange: ComputedRef<boolean>;
    hasFiscalChanges: ComputedRef<boolean>;
    requiresVersionMetadata: ComputedRef<boolean>;
    canSubmit: ComputedRef<boolean>;
    versionsToBeDeleted: ComputedRef<Fiscal[]>;
    cascadeConfirmOpen: Ref<boolean>;
    requestSubmit: () => void;
    confirmSubmit: () => void;
} {
    const fiscal = props.vehicle.currentFiscalCharacteristics;

    const today = new Date().toISOString().slice(0, 10);

    const form = useForm<VehicleEditFormShape>({
        license_plate: props.vehicle.licensePlate,
        brand: props.vehicle.brand,
        model: props.vehicle.model,
        vin: props.vehicle.vin ?? '',
        color: props.vehicle.color ?? '',
        first_french_registration_date: props.vehicle.firstFrenchRegistrationDate,
        first_origin_registration_date: props.vehicle.firstOriginRegistrationDate,
        first_economic_use_date: props.vehicle.firstEconomicUseDate,
        acquisition_date: props.vehicle.acquisitionDate,
        mileage_current: props.vehicle.mileageCurrent,
        notes: props.vehicle.notes ?? '',
        reception_category: fiscal?.receptionCategory ?? 'M1',
        vehicle_user_type: fiscal?.vehicleUserType ?? 'VP',
        body_type: fiscal?.bodyType ?? 'CI',
        seats_count: fiscal?.seatsCount ?? 5,
        energy_source: fiscal?.energySource ?? 'gasoline',
        underlying_combustion_engine_type:
            fiscal?.underlyingCombustionEngineType ?? '',
        euro_standard: fiscal?.euroStandard ?? 'euro_6d_isc_fcm',
        homologation_method: fiscal?.homologationMethod ?? 'WLTP',
        co2_wltp: fiscal?.co2Wltp ?? null,
        co2_nedc: fiscal?.co2Nedc ?? null,
        taxable_horsepower: fiscal?.taxableHorsepower ?? null,
        accepts_e85: fiscal?.acceptsE85 ?? false,
        kerb_mass: fiscal?.kerbMass ?? null,
        handicap_access: fiscal?.handicapAccess ?? false,
        m1_special_use: fiscal?.m1SpecialUse ?? false,
        n1_passenger_transport: fiscal?.n1PassengerTransport ?? false,
        n1_removable_second_row_seat: fiscal?.n1RemovableSecondRowSeat ?? false,
        n1_ski_lift_use: fiscal?.n1SkiLiftUse ?? false,
        create_new_version: false,
        effective_from: today,
        change_reason: 'recharacterization',
        change_note: '',
    });

    const changeReasonOptions: SelectOption[] = [
        { value: 'recharacterization', label: 'Reclassement fiscal' },
        { value: 'regulation_change', label: 'Changement réglementaire' },
        { value: 'other_change', label: 'Autre changement' },
    ];

    const isOtherChange = computed<boolean>(
        () => form.change_reason === 'other_change',
    );

    const hasFiscalChanges = computed<boolean>(() => {
        if (!fiscal) {
            return true;
        }

        return form.reception_category !== fiscal.receptionCategory
            || form.vehicle_user_type !== fiscal.vehicleUserType
            || form.body_type !== fiscal.bodyType
            || form.seats_count !== fiscal.seatsCount
            || form.energy_source !== fiscal.energySource
            || (form.underlying_combustion_engine_type || null)
                !== fiscal.underlyingCombustionEngineType
            || (form.euro_standard || null) !== fiscal.euroStandard
            || form.homologation_method !== fiscal.homologationMethod
            || form.co2_wltp !== fiscal.co2Wltp
            || form.co2_nedc !== fiscal.co2Nedc
            || form.taxable_horsepower !== fiscal.taxableHorsepower
            || form.accepts_e85 !== fiscal.acceptsE85
            || form.kerb_mass !== fiscal.kerbMass
            || form.handicap_access !== fiscal.handicapAccess
            || form.m1_special_use !== fiscal.m1SpecialUse
            || form.n1_passenger_transport !== fiscal.n1PassengerTransport
            || form.n1_removable_second_row_seat !== fiscal.n1RemovableSecondRowSeat
            || form.n1_ski_lift_use !== fiscal.n1SkiLiftUse;
    });

    /**
     * Only true when the user opted into a new fiscal version (checkbox)
     * AND at least one fiscal field actually changed. Drives the metadata
     * section visibility and the validation of `effective_from` /
     * `change_reason` / `change_note`.
     */
    const requiresVersionMetadata = computed<boolean>(
        () => hasFiscalChanges.value && form.create_new_version,
    );

    const canSubmit = computed<boolean>(() => {
        // Identity-only or in-place fiscal correction: no metadata required.
        if (!requiresVersionMetadata.value) {
            return true;
        }

        // New-version mode → metadata mandatory (effective date + reason, plus note if Other).
        if (form.effective_from === '') {
            return false;
        }

        if (form.change_reason === '') {
            return false;
        }

        if (isOtherChange.value && form.change_note.trim() === '') {
            return false;
        }

        return true;
    });

    /**
     * Historical versions that will be deleted by the retroactive cascade: all whose
     * `effectiveFrom >= chosen effective_from`. Only relevant when the user opted into
     * a new version (in-place mode does not touch history rows).
     */
    const versionsToBeDeleted = computed<Fiscal[]>(() => {
        if (!requiresVersionMetadata.value) {
            return [];
        }

        const effective = form.effective_from;

        if (effective === '') {
            return [];
        }

        return props.vehicle.fiscalCharacteristicsHistory.filter(
            (v) => v.effectiveFrom >= effective,
        );
    });

    const cascadeConfirmOpen = ref<boolean>(false);

    // Reset `change_note` when the reason switches away from `other_change` so a stale note
    // does not linger after the user changes their mind.
    watch(
        () => form.change_reason,
        (reason) => {
            if (reason !== 'other_change') {
                form.change_note = '';
            }
        },
    );

    // Anti-ghost-data watchers: M1/N1 flags tied to a category/body are reset to false as soon
    // as the user moves to a combination where they no longer apply. The user type also tracks
    // the reception category (M1→VP, N1→VU) since the DB enforces this 1:1 mapping.
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

    // One-way propagation: acquisition_date overwrites first_economic_use_date.
    // The reverse is not propagated. Mirrors the create form so editing the
    // acquisition date keeps the economic-use date in sync; a pre-existing
    // divergent value is preserved until the acquisition date is touched.
    watch(
        () => form.acquisition_date,
        (date) => {
            form.first_economic_use_date = date;
        },
    );

    const submit = (): void => {
        const sendMetadata = requiresVersionMetadata.value;

        form.transform((data) => ({
            ...data,
            // New-version metadata: sent only when the user opted into a new
            // version AND a fiscal field changed. Otherwise the backend
            // ignores them and performs an in-place update.
            effective_from: sendMetadata && data.effective_from !== ''
                ? data.effective_from
                : null,
            change_reason: sendMetadata && data.change_reason !== ''
                ? (data.change_reason as ChangeReason)
                : null,
            change_note: sendMetadata && data.change_note !== ''
                ? data.change_note
                : null,
            underlying_combustion_engine_type:
                data.underlying_combustion_engine_type === ''
                    ? null
                    : data.underlying_combustion_engine_type,
            euro_standard: data.euro_standard === '' ? null : data.euro_standard,
        })).patch(vehiclesUpdateRoute.url({ vehicle: props.vehicle.id }));
    };

    const requestSubmit = (): void => {
        if (!canSubmit.value) {
            return;
        }

        if (versionsToBeDeleted.value.length > 0) {
            cascadeConfirmOpen.value = true;

            return;
        }

        submit();
    };

    const confirmSubmit = (): void => {
        cascadeConfirmOpen.value = false;
        submit();
    };

    return {
        form,
        changeReasonOptions,
        isOtherChange,
        hasFiscalChanges,
        requiresVersionMetadata,
        canSubmit,
        versionsToBeDeleted,
        cascadeConfirmOpen,
        requestSubmit,
        confirmSubmit,
    };
}
