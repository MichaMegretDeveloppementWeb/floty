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
 * Form Inertia + UI state du modal de **création** d'une nouvelle VFC
 * depuis la modale Historique (parallèle de
 * {@link useVfcEditForm} pour l'édition).
 *
 * Le composable :
 *   - **P2 · préremplit** le formulaire avec la VFC actuelle quand
 *     `props.current` est fourni (cohérent · 95% du temps un seul
 *     champ change, ex. CO₂ recalculé). Les champs de plage
 *     (`effective_from`, `effective_to`) restent vides pour forcer le
 *     choix de date explicite. Si `props.current` est null (véhicule
 *     sans VFC active), on retombe sur les valeurs par défaut.
 *   - expose les motifs sélectionnables (tous sauf `initial_creation`
 *     qui est réservé au système · création réelle au moment de la
 *     première VFC du véhicule, faite par CreateVehicleAction),
 *   - calcule en live l'impact sur les voisines via
 *     `computeVfcUpdateImpact()` en passant `editingId = null` (pas
 *     d'exclusion : on simule l'insertion d'une nouvelle plage
 *     dans l'historique complet),
 *   - dispatche le submit POST puis ferme la modale au success.
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
    // P2 · construit l'état initial du formulaire à partir de la VFC
    // actuelle si fournie · l'utilisateur n'a qu'à ajuster les champs
    // qui changent et choisir la date d'effet. Les champs de plage
    // (`effective_from`, `effective_to`) restent vides pour forcer un
    // choix de date explicite (sinon on créerait une plage identique
    // qui serait refusée par le backend).
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

    // À chaque ouverture, on réinitialise le formulaire en repartant
    // de la VFC actuelle (si fournie). Bénéfice UX · si l'utilisateur
    // ferme la modale après avoir modifié des champs, la réouverture
    // repart sur un état frais préfilled, pas sur les valeurs orphelines
    // de la session précédente.
    watch(open, (isOpen) => {
        if (isOpen) {
            Object.assign(form, buildInitialState());
            form.clearErrors();
        }
    });

    // Watchers anti-données fantômes (mêmes règles qu'en édition).
    watch(
        () => form.reception_category,
        (cat) => {
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

        // R4 (parité backend) : on refuse une plage strictement contenue
        // dans une VFC existante. Le backend ré-attrape ce cas, mais le
        // bloquer côté UI évite un aller-retour serveur fastidieux et
        // affiche le message contextuel directement dans la modale.
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
 * Helper utilisé par le partial parent pour ouvrir/fermer la modale
 * de création. Pas de référence à une VFC en cours puisque c'est une
 * création.
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
