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
 * Form Inertia + UI state de la page Edit véhicule.
 *
 * Edit ne sert qu'aux **changements réels** du véhicule dans le temps :
 * il crée systématiquement une nouvelle ligne d'historique fiscal. Les
 * corrections de saisie sur une VFC existante passent exclusivement
 * par la modale Historique de la page véhicule.
 *
 * Le composable :
 *   - pré-remplit le `useForm` à partir de `props.vehicle` + sa VFC
 *     courante,
 *   - expose la liste des motifs sélectionnables (`changeReasonOptions`,
 *     les 3 motifs `userSelectableForNewVersion` côté backend),
 *   - calcule `isOtherChange` pour piloter l'affichage conditionnel de
 *     la note explicative,
 *   - détecte si au moins un champ fiscal a changé (`hasFiscalChanges`)
 *     — utilisé pour bloquer la soumission sans modification réelle,
 *   - calcule la liste des versions historiques qui seront supprimées
 *     (`versionsToBeDeleted`) si la date d'effet remonte avant elles,
 *   - expose `requestSubmit()` qui ouvre la ConfirmModal si la cascade
 *     s'applique, sinon soumet directement.
 */
export function useVehicleEditForm(props: { vehicle: Vehicle }): {
    form: InertiaForm<VehicleEditFormShape>;
    changeReasonOptions: SelectOption[];
    isOtherChange: ComputedRef<boolean>;
    hasFiscalChanges: ComputedRef<boolean>;
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
            || form.taxable_horsepower !== fiscal.taxableHorsepower;
    });

    const canSubmit = computed<boolean>(() => {
        if (!hasFiscalChanges.value) {
            return false;
        }

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
     * Versions historiques qui seront supprimées par la cascade
     * rétroactive : toutes celles dont `effectiveFrom >= effective_from
     * choisi`. La VFC courante est incluse (puisqu'elle commence par
     * définition après `previousVfc.effective_to + 1`, et donc est
     * postérieure ou égale à toute date que l'utilisateur peut choisir
     * sauf si choisie strictement antérieure à la courante).
     */
    const versionsToBeDeleted = computed<Fiscal[]>(() => {
        const effective = form.effective_from;

        if (effective === '') {
            return [];
        }

        return props.vehicle.fiscalCharacteristicsHistory.filter(
            (v) => v.effectiveFrom >= effective,
        );
    });

    const cascadeConfirmOpen = ref<boolean>(false);

    // Reset du change_note quand on change de motif vers autre chose
    // que `other_change` (sinon, si l'utilisateur avait saisi une note
    // pour le motif Autre puis change, la note traîne).
    watch(
        () => form.change_reason,
        (reason) => {
            if (reason !== 'other_change') {
                form.change_note = '';
            }
        },
    );

    const submit = (): void => {
        form.transform((data) => ({
            ...data,
            change_reason: data.change_reason === '' ? null : data.change_reason as ChangeReason,
            change_note: data.change_note === '' ? null : data.change_note,
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
        canSubmit,
        versionsToBeDeleted,
        cascadeConfirmOpen,
        requestSubmit,
        confirmSubmit,
    };
}
