import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import type { VfcEditFormShape } from '@/pages/User/Vehicles/Show/forms';
import { update as vfcUpdateRoute } from '@/routes/user/vehicle-fiscal-characteristics';

type Vfc = App.Data.User.Vehicle.VehicleFiscalCharacteristicsData;
type ChangeReason = App.Enums.Vehicle.FiscalCharacteristicsChangeReason;
type SelectOption = { value: string; label: string };

/**
 * Form Inertia + UI state du modal d'édition d'une VFC isolée.
 *
 * Le composable :
 *   - synchronise le `useForm` quand `props.editing` change (la
 *     modale est partagée entre toutes les lignes de l'historique),
 *   - expose les motifs sélectionnables (tous sauf `initial_creation`
 *     qui est réservé au système) — l'historique permet plus de
 *     motifs que le flux Edit véhicule,
 *   - calcule `canSubmit` (bornes valides + change_note requise si
 *     motif `other_change`),
 *   - dispatche le submit PATCH puis ferme la modale au success.
 */
export function useVfcEditForm(
    props: { editing: Vfc | null },
    open: Ref<boolean>,
): {
    form: InertiaForm<VfcEditFormShape>;
    changeReasonOptions: SelectOption[];
    isOtherChange: ComputedRef<boolean>;
    canSubmit: ComputedRef<boolean>;
    submit: () => void;
} {
    const form = useForm<VfcEditFormShape>({
        reception_category: 'M1',
        vehicle_user_type: 'VP',
        body_type: 'CI',
        seats_count: 5,
        energy_source: 'gasoline',
        euro_standard: 'euro_6d_isc_fcm',
        pollutant_category: 'category_1',
        homologation_method: 'WLTP',
        co2_wltp: null,
        co2_nedc: null,
        taxable_horsepower: null,
        effective_from: '',
        effective_to: '',
        change_reason: 'recharacterization',
        change_note: '',
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
                form.euro_standard = value.euroStandard ?? '';
                form.pollutant_category = value.pollutantCategory;
                form.homologation_method = value.homologationMethod;
                form.co2_wltp = value.co2Wltp;
                form.co2_nedc = value.co2Nedc;
                form.taxable_horsepower = value.taxableHorsepower;
                form.effective_from = value.effectiveFrom;
                form.effective_to = value.effectiveTo ?? '';
                form.change_reason = value.changeReason === 'initial_creation'
                    ? 'recharacterization'
                    : value.changeReason;
                form.change_note = value.changeNote ?? '';
            }

            form.clearErrors();
        },
    );

    const isOtherChange = computed<boolean>(
        () => form.change_reason === 'other_change',
    );

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

        return true;
    });

    const submit = (): void => {
        if (!canSubmit.value || !props.editing) {
            return;
        }

        form.transform((data) => ({
            ...data,
            change_reason: data.change_reason as ChangeReason,
            change_note: data.change_note === '' ? null : data.change_note,
            effective_to: data.effective_to === '' ? null : data.effective_to,
            euro_standard: data.euro_standard === '' ? null : data.euro_standard,
        })).patch(
            vfcUpdateRoute.url({ vehicleFiscalCharacteristic: props.editing.id }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    open.value = false;
                },
            },
        );
    };

    return {
        form,
        changeReasonOptions,
        isOtherChange,
        canSubmit,
        submit,
    };
}

/**
 * Helper utilisé par le partial pour ouvrir/fermer la modale et
 * traquer la VFC en cours d'édition. Permet de garder la logique
 * d'ouverture hors du `<script setup>` du modal lui-même.
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
