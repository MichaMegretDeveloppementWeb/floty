import type { InertiaForm } from '@inertiajs/vue3';
import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import {
    destroy as detailSuggestionsDestroyRoute,
    store as detailSuggestionsStoreRoute,
} from '@/routes/user/vehicle-event-detail-suggestions';
import {
    destroy as natureSuggestionsDestroyRoute,
    store as natureSuggestionsStoreRoute,
} from '@/routes/user/vehicle-event-natures';
import {
    store as vehicleEventsStoreRoute,
    update as vehicleEventsUpdateRoute,
} from '@/routes/user/vehicle-events';
import { formatDayLongFr } from '@/Utils/format/formatDayLongFr';
import { centsToEuros, eurosToCents } from '@/Utils/format/money';
import {
    cleanCustomCategories,
    hasDuplicateCategories,
    normalizeCategory,
} from '@/Utils/vehicleEventCategories';

type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;

export type DeletableNatureSuggestion = { id: number; label: string };

export type NatureSuggestions = {
    /** Frozen fiscally-reductive catalogue block. */
    reductive: string[];
    /** Every other catalogue suggestion (base + user additions). */
    other: string[];
    /** Same non-reductive set with ids · only the reductive block is mandatory. */
    deletable: DeletableNatureSuggestion[];
};

type FormShape = {
    // Free name of the event, always required.
    title: string;
    // Natures (UI « Nature »), at least one, unlimited.
    categories: string[];
    // Detail lines (section « Détails »), optional, unlimited.
    details: string[];
    // Garage name (free text, optional); feeds the autocomplete on save.
    garage: string;
    // Postal code (optional, independent from the garage).
    postal_code: string;
    // Unavailability flag; locked (forced true) when the event is reductive.
    implies_unavailability: boolean;
    start_date: string;
    end_date: string;
    description: string;
    // Optional cost in EUROS (converted to amount_cents on submit); costs only.
    amount: number | null;
};

type DateRange = { startDate: string | null; endDate: string | null };

/**
 * Counts ISO dates in `busyDates` (days already assigned to an active contract) that fall
 * within the user's range.
 *
 * VehicleEvent ↔ contract cohabitation (ADR-0019): the range MAY overlap a contract;
 * this function feeds the modal's pedagogical info banner, it does not block input.
 *
 * Semantics:
 *   - `startDate === null` → 0 (incomplete range)
 *   - `!ongoing && endDate === null` → 0 (idem)
 *   - `ongoing === true` → count all `busyDates >= startDate` (open-ended range, matching
 *     backend `end_date IS NULL` for an ongoing vehicle event)
 *   - otherwise → count `busyDates ∈ [startDate, endDate]` inclusive
 *
 * Pure to ease unit testing (no composable access).
 */
export function countConflictDaysInRange(
    busyDates: ReadonlyArray<string>,
    startDate: string | null,
    endDate: string | null,
    ongoing: boolean,
): number {
    if (startDate === null) {
        return 0;
    }

    if (!ongoing && endDate === null) {
        return 0;
    }

    return busyDates.filter((d) => {
        if (d < startDate) {
            return false;
        }

        if (!ongoing && endDate !== null && d > endDate) {
            return false;
        }

        return true;
    }).length;
}

/**
 * True when at least one nature belongs to the reductive catalogue block.
 * Matching is trimmed + case-insensitive, mirroring the backend
 * `EventNatureFiscalResolver`. Pure to ease unit testing.
 */
export function hasReductiveNature(
    natures: ReadonlyArray<string>,
    reductiveLabels: ReadonlyArray<string>,
): boolean {
    const reductiveKeys = new Set(reductiveLabels.map(normalizeCategory));

    return natures.some((nature) => {
        const key = normalizeCategory(nature);

        return key !== '' && reductiveKeys.has(key);
    });
}

/**
 * Inertia form + UI state for the vehicle event create/edit page (refonte
 * type → nature).
 *
 *   - the event identity is its free name + natures; the reductive natures of
 *     the catalogue drive `selectedIsReductive` (fiscal-effect banner) and
 *     lock `implies_unavailability` to true (the server forces it anyway),
 *   - synchronises `range` and `ongoing` when `props.editing` changes (create vs edit mode),
 *   - validates client-side at submit and scrolls to the topmost field error,
 *   - applies `payloadTransform` (range+ongoing → snake_case backend),
 *   - dispatches submit (POST store or PATCH update); the controller redirects
 *     to the vehicle "Événements" tab on success, Inertia keeps the page and
 *     `form.errors` on validation failure.
 */
export function useVehicleEventForm(
    props: {
        vehicleId: number;
        editing: VehicleEvent | null;
        busyDates: string[];
        /** Catalogue suggestions, two blocks (reductive frozen + others). */
        natureSuggestions?: NatureSuggestions;
        /** Suggestion catalogue of the « Détails » lines (fully user-managed). */
        detailSuggestions?: DeletableNatureSuggestion[];
    },
    options: {
        /** Files queued in create mode, sent with the multipart create request. */
        pendingDocuments?: Ref<File[]>;
        /**
         * ISO `YYYY-MM-DD` pre-selected in create mode (start = end), set when
         * the user opened the form from a specific timeline day. Ignored in edit.
         */
        initialDate?: string;
    } = {},
): {
    /**
     * Year to display in the calendar on opening. Create mode: current calendar year.
     * Edit mode: the year of the edited vehicle event's `start_date`
     * (otherwise the calendar stays on the current year while editing an older vehicle event).
     */
    viewYear: Ref<number>;
    form: InertiaForm<FormShape>;
    range: Ref<DateRange>;
    ongoing: Ref<boolean>;
    initialMonth: Ref<number>;
    isEditing: ComputedRef<boolean>;
    /**
     * True in create mode opened from a specific timeline day (`initialDate` set):
     * the period section is replaced by a fixed single-day reminder.
     */
    isFixedDate: ComputedRef<boolean>;
    /** Long French label of the fixed day (empty unless `isFixedDate`). */
    fixedDateLabel: ComputedRef<string>;
    /** ≥ 1 nature belongs to the reductive catalogue block. */
    selectedIsReductive: ComputedRef<boolean>;
    conflictDaysCount: ComputedRef<number>;
    /** Server error for the amount (payload key `amount_cents`, outside FormShape). */
    amountError: ComputedRef<string | undefined>;
    /** « Ajouter à la liste » : confirmation puis persistance d'une saisie libre. */
    natureToAdd: Ref<string | null>;
    addNatureModalOpen: Ref<boolean>;
    requestAddNature: (label: string) => void;
    confirmAddNature: () => void;
    /** Retrait d'une suggestion non réductrice (confirmation puis DELETE). */
    suggestionToRemove: Ref<DeletableNatureSuggestion | null>;
    removeSuggestionModalOpen: Ref<boolean>;
    requestRemoveSuggestion: (suggestion: DeletableNatureSuggestion) => void;
    confirmRemoveSuggestion: () => void;
    /** Mêmes gestes pour le catalogue des « Détails ». */
    detailToAdd: Ref<string | null>;
    addDetailModalOpen: Ref<boolean>;
    requestAddDetail: (label: string) => void;
    confirmAddDetail: () => void;
    detailSuggestionToRemove: Ref<DeletableNatureSuggestion | null>;
    removeDetailSuggestionModalOpen: Ref<boolean>;
    requestRemoveDetailSuggestion: (suggestion: DeletableNatureSuggestion) => void;
    confirmRemoveDetailSuggestion: () => void;
    submit: () => void;
} {
    const form = useForm<FormShape>({
        title: '',
        categories: [],
        details: [],
        garage: '',
        postal_code: '',
        implies_unavailability: true,
        start_date: '',
        end_date: '',
        description: '',
        amount: null,
    });

    const range = ref<DateRange>({ startDate: null, endDate: null });
    const ongoing = ref<boolean>(false);

    // Initial DateRangePicker view (month + year): derived from the edited vehicle event's startDate
    // so the calendar opens on the selected period. Create mode: current calendar month + year.
    const now = new Date();
    const initialMonth = ref<number>(now.getMonth() + 1);
    const viewYear = ref<number>(now.getFullYear());

    watch(
        () => props.editing,
        (value) => {
            if (value) {
                form.title = value.title;
                form.categories = [...value.categories];
                form.details = [...value.details];
                form.garage = value.garage ?? '';
                form.postal_code = value.postalCode ?? '';
                form.implies_unavailability = value.impliesUnavailability;
                form.description = value.description ?? '';
                form.amount = centsToEuros(value.amountCents);
                range.value = {
                    startDate: value.startDate,
                    endDate: value.endDate,
                };
                ongoing.value = value.endDate === null;
                // Open the calendar on the year AND month of the edited vehicle event's startDate.
                // Without this, editing a 2024 vehicle event showed an empty 2026 calendar and the
                // user could not see the selected period.
                viewYear.value = Number(value.startDate.slice(0, 4));
                initialMonth.value = Number(value.startDate.slice(5, 7));
            } else {
                form.reset();

                if (options.initialDate) {
                    // Create from a specific timeline day: pre-select start = end
                    // and open the calendar on that day.
                    range.value = { startDate: options.initialDate, endDate: options.initialDate };
                    ongoing.value = false;
                    viewYear.value = Number(options.initialDate.slice(0, 4));
                    initialMonth.value = Number(options.initialDate.slice(5, 7));
                } else {
                    range.value = { startDate: null, endDate: null };
                    ongoing.value = false;
                    // Reset to the calendar present (create mode).
                    const today = new Date();
                    viewYear.value = today.getFullYear();
                    initialMonth.value = today.getMonth() + 1;
                }
            }

            form.clearErrors();
        },
        // Immediate: on the dedicated form pages `props.editing` is set once at
        // mount (it never changes), so the watcher must run on mount to
        // pre-fill (edit) or reset (create) the form.
        { immediate: true },
    );

    const isEditing = computed<boolean>(() => props.editing !== null);

    const isFixedDate = computed<boolean>(
        () => props.editing === null && options.initialDate != null && options.initialDate !== '',
    );

    const fixedDateLabel = computed<string>(() =>
        options.initialDate != null && options.initialDate !== ''
            ? formatDayLongFr(options.initialDate)
            : '',
    );

    const selectedIsReductive = computed<boolean>(() =>
        hasReductiveNature(form.categories, props.natureSuggestions?.reductive ?? []),
    );

    // A reductive event always implies an unavailability: lock the checkbox on
    // (the server forces the same rule, this keeps the UI truthful live).
    watch(selectedIsReductive, (isReductive) => {
        if (isReductive) {
            form.implies_unavailability = true;
        }
    });

    const hasCategoryDuplicates = computed<boolean>(() =>
        hasDuplicateCategories(form.categories),
    );

    const hasDetailDuplicates = computed<boolean>(() =>
        hasDuplicateCategories(form.details),
    );

    /**
     * Client-side validation at submit time. The button stays enabled: every
     * problem is reported as a field error at its own spot (then the form
     * scrolls to the topmost one), so the user always knows what blocks.
     */
    const validateClientSide = (): boolean => {
        form.clearErrors();

        if (range.value.startDate === null) {
            form.setError('start_date', 'La date de début est obligatoire.');
        }

        if (!ongoing.value && range.value.endDate === null) {
            form.setError('end_date', 'La date de fin est obligatoire (ou cochez « Événement en cours »).');
        }

        if (form.title.trim() === '') {
            form.setError('title', "Le nom de l'événement est obligatoire.");
        }

        if (cleanCustomCategories(form.categories).length === 0) {
            form.setError('categories', 'Au moins une nature est obligatoire.');
        } else if (hasCategoryDuplicates.value) {
            form.setError('categories', 'Cette nature est déjà présente.');
        }

        if (hasDetailDuplicates.value) {
            form.setError('details', 'Ce détail est déjà présent.');
        }

        const postalCode = form.postal_code.trim();

        if (postalCode !== '' && !/^\d{4,6}$/.test(postalCode)) {
            form.setError('postal_code', 'Le code postal doit être une suite de 4 à 6 chiffres.');
        }

        return !form.hasErrors;
    };

    /** Scroll to the topmost visible field error (InputError has role=alert). */
    const scrollToFirstError = (): void => {
        void nextTick(() => {
            document.querySelector('[role="alert"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    };

    const amountError = computed<string | undefined>(
        () => (form.errors as Record<string, string | undefined>).amount_cents,
    );

    const conflictDaysCount = computed<number>(() =>
        countConflictDaysInRange(
            props.busyDates,
            range.value.startDate,
            range.value.endDate,
            ongoing.value,
        ),
    );

    const payloadTransform = (data: FormShape): Record<string, unknown> => ({
        title: data.title.trim(),
        categories: cleanCustomCategories(data.categories),
        details: cleanCustomCategories(data.details),
        garage: data.garage.trim() === '' ? null : data.garage.trim(),
        postal_code: data.postal_code.trim() === '' ? null : data.postal_code.trim(),
        // A reductive event always implies an unavailability (server-forced).
        implies_unavailability: selectedIsReductive.value ? true : data.implies_unavailability,
        start_date: range.value.startDate,
        end_date: ongoing.value ? null : range.value.endDate,
        description: data.description === '' ? null : data.description,
        amount_cents: eurosToCents(data.amount),
    });

    // « Ajouter à la liste » / retrait d'une suggestion : confirmation via
    // modale (état porté ici), puis requête avec partial reload de
    // `natureSuggestions` seul (preserveState garde le formulaire intact).
    const natureToAdd = ref<string | null>(null);
    const addNatureModalOpen = ref<boolean>(false);
    const suggestionToRemove = ref<DeletableNatureSuggestion | null>(null);
    const removeSuggestionModalOpen = ref<boolean>(false);

    const requestAddNature = (label: string): void => {
        natureToAdd.value = label;
        addNatureModalOpen.value = true;
    };

    const confirmAddNature = (): void => {
        if (natureToAdd.value === null) {
            return;
        }

        addNatureModalOpen.value = false;

        router.post(
            natureSuggestionsStoreRoute.url(),
            { label: natureToAdd.value },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['natureSuggestions', 'flash'],
                onFinish: () => {
                    natureToAdd.value = null;
                },
            },
        );
    };

    const requestRemoveSuggestion = (suggestion: DeletableNatureSuggestion): void => {
        suggestionToRemove.value = suggestion;
        removeSuggestionModalOpen.value = true;
    };

    const detailToAdd = ref<string | null>(null);
    const addDetailModalOpen = ref<boolean>(false);
    const detailSuggestionToRemove = ref<DeletableNatureSuggestion | null>(null);
    const removeDetailSuggestionModalOpen = ref<boolean>(false);

    const requestAddDetail = (label: string): void => {
        detailToAdd.value = label;
        addDetailModalOpen.value = true;
    };

    const confirmAddDetail = (): void => {
        if (detailToAdd.value === null) {
            return;
        }

        addDetailModalOpen.value = false;

        router.post(
            detailSuggestionsStoreRoute.url(),
            { label: detailToAdd.value },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['detailSuggestions', 'flash'],
                onFinish: () => {
                    detailToAdd.value = null;
                },
            },
        );
    };

    const requestRemoveDetailSuggestion = (suggestion: DeletableNatureSuggestion): void => {
        detailSuggestionToRemove.value = suggestion;
        removeDetailSuggestionModalOpen.value = true;
    };

    const confirmRemoveDetailSuggestion = (): void => {
        if (detailSuggestionToRemove.value === null) {
            return;
        }

        removeDetailSuggestionModalOpen.value = false;

        router.delete(
            detailSuggestionsDestroyRoute.url({
                vehicleEventDetailSuggestion: detailSuggestionToRemove.value.id,
            }),
            {
                preserveState: true,
                preserveScroll: true,
                only: ['detailSuggestions', 'flash'],
                onFinish: () => {
                    detailSuggestionToRemove.value = null;
                },
            },
        );
    };

    const confirmRemoveSuggestion = (): void => {
        if (suggestionToRemove.value === null) {
            return;
        }

        removeSuggestionModalOpen.value = false;

        router.delete(
            natureSuggestionsDestroyRoute.url({ vehicleEventNature: suggestionToRemove.value.id }),
            {
                preserveState: true,
                preserveScroll: true,
                only: ['natureSuggestions', 'flash'],
                onFinish: () => {
                    suggestionToRemove.value = null;
                },
            },
        );
    };

    const submit = (): void => {
        if (!validateClientSide()) {
            scrollToFirstError();

            return;
        }

        if (isEditing.value && props.editing) {
            form.transform(payloadTransform).patch(
                vehicleEventsUpdateRoute.url({
                    vehicleEvent: props.editing.id,
                }),
                { onError: scrollToFirstError },
            );

            return;
        }

        form.transform((data) => ({
            ...payloadTransform(data),
            vehicle_id: props.vehicleId,
            // Files queued during creation travel with the multipart request;
            // Inertia switches to FormData automatically when files are present.
            documents: options.pendingDocuments?.value ?? [],
        })).post(vehicleEventsStoreRoute.url(), { onError: scrollToFirstError });
    };

    return {
        viewYear,
        form,
        range,
        ongoing,
        initialMonth,
        isEditing,
        isFixedDate,
        fixedDateLabel,
        selectedIsReductive,
        conflictDaysCount,
        amountError,
        natureToAdd,
        addNatureModalOpen,
        requestAddNature,
        confirmAddNature,
        suggestionToRemove,
        removeSuggestionModalOpen,
        requestRemoveSuggestion,
        confirmRemoveSuggestion,
        detailToAdd,
        addDetailModalOpen,
        requestAddDetail,
        confirmAddDetail,
        detailSuggestionToRemove,
        removeDetailSuggestionModalOpen,
        requestRemoveDetailSuggestion,
        confirmRemoveDetailSuggestion,
        submit,
    };
}
