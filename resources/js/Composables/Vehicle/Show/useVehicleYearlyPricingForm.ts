import type { InertiaForm } from '@inertiajs/vue3';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { destroy as pricingDestroyRoute, store as pricingStoreRoute } from '@/routes/user/vehicle-yearly-pricings';

type Pricing = App.Data.User.Vehicle.VehicleYearlyPricingData;

export type PricingFormShape = {
    year: number;
    dailyRateEuros: number | null;
    weeklyRateEuros: number | null;
    monthlyRateEuros: number | null;
};

/**
 * Construit l'état initial du formulaire selon le mode :
 *   - édition (`editing` non-null) : on convertit cents → € pour les
 *     trois tarifs, et l'année provient du DTO existant (verrouillée
 *     côté UI),
 *   - création : on précharge `currentYear` si elle est dans la liste
 *     des années disponibles, sinon on prend la première année dispo,
 *     en dernier recours `currentYear` (cas dégénéré où la liste est
 *     vide · le bouton sera désactivé en amont).
 *
 * Fonction pure (testable sans mocker Inertia).
 */
export function buildPricingFormInitialState(
    editing: Pricing | null,
    availableYears: ReadonlyArray<number>,
    currentYear: number,
): PricingFormShape {
    if (editing) {
        return {
            year: editing.year,
            dailyRateEuros: editing.dailyRateCents / 100,
            weeklyRateEuros: editing.weeklyRateCents / 100,
            monthlyRateEuros: editing.monthlyRateCents / 100,
        };
    }

    const fallback = availableYears.includes(currentYear)
        ? currentYear
        : (availableYears[0] ?? currentYear);

    return {
        year: fallback,
        dailyRateEuros: null,
        weeklyRateEuros: null,
        monthlyRateEuros: null,
    };
}

/**
 * Règle de validation côté UI · exécutée à chaque frappe pour activer /
 * désactiver le bouton « Enregistrer ». Le backend ré-attrape ces cas
 * via Spatie Data, mais bloquer en amont évite l'aller-retour serveur
 * sur les saisies évidentes (champs vides, négatifs).
 *
 * Note : un tarif à 0 est *valide* (cas véhicule de courtoisie / usage
 * interne gratuit, cf. `permet_un_tarif_zero_pour_les_vehicules_en_usage_gratuit`
 * dans `UpsertVehicleYearlyPricingActionTest`).
 */
export function isPricingFormValid(form: PricingFormShape, processing: boolean): boolean {
    if (processing) {
        return false;
    }

    if (form.year <= 0) {
        return false;
    }

    if (
        form.dailyRateEuros === null
        || form.weeklyRateEuros === null
        || form.monthlyRateEuros === null
    ) {
        return false;
    }

    if (
        form.dailyRateEuros < 0
        || form.weeklyRateEuros < 0
        || form.monthlyRateEuros < 0
    ) {
        return false;
    }

    return true;
}

/**
 * Convertit le formulaire affiché en € vers le payload backend en cents
 * (snake_case, attendu par Spatie Data via `MapInputName(SnakeCaseMapper)`).
 *
 * `Math.round()` est crucial : `1.10 * 100 === 110.00000000000001` en
 * IEEE 754 et `Math.trunc()` retournerait 109 · on perd un centime sur
 * tous les tarifs « ronds » saisis avec décimales.
 */
export function transformPricingFormToPayload(form: PricingFormShape): {
    year: number;
    daily_rate_cents: number;
    weekly_rate_cents: number;
    monthly_rate_cents: number;
} {
    return {
        year: form.year,
        daily_rate_cents: Math.round((form.dailyRateEuros ?? 0) * 100),
        weekly_rate_cents: Math.round((form.weeklyRateEuros ?? 0) * 100),
        monthly_rate_cents: Math.round((form.monthlyRateEuros ?? 0) * 100),
    };
}

/**
 * Form Inertia + UI state du modal de **création / édition** d'un tarif
 * jour/semaine/mois pour une année donnée (Phase 14 V1.2).
 *
 * Convention : les valeurs sont **affichées en €** côté UI mais
 * **transmises en cents** au backend (`unsignedInteger` en base). La
 * conversion bidirectionnelle est centralisée dans ce composable :
 * cents → € à l'init quand on édite un pricing existant, et
 * € → cents au submit (`Math.round(€ * 100)`).
 *
 * Le backend route store sur `(vehicle_id, year)` est idempotent
 * (`updateOrCreate`), donc le même endpoint sert les deux modes.
 */
export function useVehicleYearlyPricingForm(
    props: {
        vehicleId: number;
        availableYears: ReadonlyArray<number>;
        editing: Pricing | null;
    },
    open: Ref<boolean>,
): {
    form: InertiaForm<PricingFormShape>;
    isEditing: ComputedRef<boolean>;
    yearOptions: ComputedRef<ReadonlyArray<{ value: number; label: string }>>;
    canSubmit: ComputedRef<boolean>;
    submit: () => void;
} {
    const currentYear = new Date().getFullYear();

    const buildInitialState = (): PricingFormShape =>
        buildPricingFormInitialState(props.editing, props.availableYears, currentYear);

    const form = useForm<PricingFormShape>(buildInitialState());

    const isEditing = computed<boolean>(() => props.editing !== null);

    const yearOptions = computed<ReadonlyArray<{ value: number; label: string }>>(() => {
        // En édition, l'année est verrouillée mais on l'expose tout de
        // même comme seule option pour que le SelectInput puisse afficher
        // le label correspondant.
        if (isEditing.value && props.editing) {
            return [{ value: props.editing.year, label: String(props.editing.year) }];
        }

        return props.availableYears.map((year) => ({
            value: year,
            label: String(year),
        }));
    });

    // À chaque ouverture, on réinitialise le formulaire avec les valeurs
    // adaptées au mode (création vs édition).
    watch(open, (isOpen) => {
        if (isOpen) {
            Object.assign(form, buildInitialState());
            form.clearErrors();
        }
    });

    const canSubmit = computed<boolean>(() =>
        isPricingFormValid(
            {
                year: form.year,
                dailyRateEuros: form.dailyRateEuros,
                weeklyRateEuros: form.weeklyRateEuros,
                monthlyRateEuros: form.monthlyRateEuros,
            },
            form.processing,
        ),
    );

    const submit = (): void => {
        if (!canSubmit.value) {
            return;
        }

        form.transform(transformPricingFormToPayload).post(
            pricingStoreRoute.url({ vehicle: props.vehicleId }),
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
        isEditing,
        yearOptions,
        canSubmit,
        submit,
    };
}

/**
 * Helper utilisé par le partial parent pour piloter la modale de
 * création / édition. L'état `editing` est `null` en mode création et
 * pointe sur le DTO existant en mode édition. La modale enfant appelle
 * `useVehicleYearlyPricingForm()` qui s'adapte au mode automatiquement.
 */
export function useVehiclePricingFormModalState(): {
    open: Ref<boolean>;
    editing: Ref<App.Data.User.Vehicle.VehicleYearlyPricingData | null>;
    requestCreate: () => void;
    requestEdit: (pricing: App.Data.User.Vehicle.VehicleYearlyPricingData) => void;
} {
    const open = ref<boolean>(false);
    const editing = ref<App.Data.User.Vehicle.VehicleYearlyPricingData | null>(null);

    const requestCreate = (): void => {
        editing.value = null;
        open.value = true;
    };

    const requestEdit = (pricing: App.Data.User.Vehicle.VehicleYearlyPricingData): void => {
        editing.value = pricing;
        open.value = true;
    };

    return { open, editing, requestCreate, requestEdit };
}

/**
 * Helper pilotant la modale de confirmation de suppression. Stocke le
 * pricing à supprimer et expose la méthode `confirmDelete()` qui
 * dispatche le DELETE Wayfinder vers le backend.
 */
export function useVehiclePricingDeleteState(vehicleId: number): {
    open: Ref<boolean>;
    deleting: Ref<App.Data.User.Vehicle.VehicleYearlyPricingData | null>;
    processing: Ref<boolean>;
    requestDelete: (pricing: App.Data.User.Vehicle.VehicleYearlyPricingData) => void;
    confirmDelete: () => void;
} {
    const open = ref<boolean>(false);
    const deleting = ref<App.Data.User.Vehicle.VehicleYearlyPricingData | null>(null);
    const processing = ref<boolean>(false);

    const requestDelete = (pricing: App.Data.User.Vehicle.VehicleYearlyPricingData): void => {
        deleting.value = pricing;
        open.value = true;
    };

    const confirmDelete = (): void => {
        if (deleting.value === null) {
            return;
        }

        processing.value = true;

        router.delete(
            pricingDestroyRoute.url({ vehicle: vehicleId, year: deleting.value.year }),
            {
                preserveScroll: true,
                onFinish: () => {
                    processing.value = false;
                    open.value = false;
                    deleting.value = null;
                },
            },
        );
    };

    return { open, deleting, processing, requestDelete, confirmDelete };
}
