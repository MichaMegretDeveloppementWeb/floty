import { router } from '@inertiajs/vue3';
import { computed, ref, toValue } from 'vue';
import type { ComputedRef, MaybeRefOrGetter, Ref } from 'vue';
import { destroy as vehicleEventsDestroyRoute } from '@/routes/user/vehicle-events';
import { formatDayLongFr } from '@/Utils/format/formatDayLongFr';
import { vehicleEventDisplayCategory } from '@/Utils/labels/vehicleEventEnumLabels';

type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;

type StatTone = 'slate' | 'blue' | 'emerald' | 'amber' | 'rose';

/** A labelled status surfaced in the "Nature" strip of the detail page. */
export type VehicleEventStatus = {
    value: string;
    caption: string;
    tone: StatTone;
};

/**
 * State and derivations for the vehicle event detail page: the delete
 * confirmation modal, the DELETE request (the controller redirects to the
 * vehicle "Événements" timeline tab on success), the period visual (start /
 * end / duration) and the fiscal & availability statuses.
 */
export function useVehicleEventShow(event: MaybeRefOrGetter<VehicleEvent>): {
    deleteModalOpen: Ref<boolean>;
    deleting: Ref<boolean>;
    isOngoing: ComputedRef<boolean>;
    startLabel: ComputedRef<string>;
    endLabel: ComputedRef<string | null>;
    durationLabel: ComputedRef<string>;
    category: ComputedRef<string>;
    fiscalStatus: ComputedRef<VehicleEventStatus>;
    availabilityStatus: ComputedRef<VehicleEventStatus>;
    openDelete: () => void;
    closeDelete: () => void;
    confirmDelete: () => void;
} {
    const deleteModalOpen = ref<boolean>(false);
    const deleting = ref<boolean>(false);

    const openDelete = (): void => {
        deleteModalOpen.value = true;
    };

    const closeDelete = (): void => {
        deleteModalOpen.value = false;
    };

    const confirmDelete = (): void => {
        deleting.value = true;

        router.delete(
            vehicleEventsDestroyRoute.url({ vehicleEvent: toValue(event).id }),
            {
                onFinish: () => {
                    deleting.value = false;
                    deleteModalOpen.value = false;
                },
            },
        );
    };

    const isOngoing = computed<boolean>(() => toValue(event).endDate === null);

    const startLabel = computed<string>(() => formatDayLongFr(toValue(event).startDate));

    const endLabel = computed<string | null>(() => {
        const end = toValue(event).endDate;

        return end === null ? null : formatDayLongFr(end);
    });

    const durationLabel = computed<string>(() => {
        const current = toValue(event);

        if (current.endDate === null) {
            return 'En cours';
        }

        return `${current.daysCount} jour${current.daysCount > 1 ? 's' : ''}`;
    });

    const category = computed<string>(() => vehicleEventDisplayCategory(toValue(event)));

    const fiscalStatus = computed<VehicleEventStatus>(() => {
        const hasImpact = toValue(event).hasFiscalImpact;

        return {
            value: hasImpact ? 'Réductrice' : 'Sans effet',
            caption: hasImpact
                ? 'Réduit le prorata fiscal sur la période'
                : 'Aucun impact sur le calcul fiscal',
            tone: hasImpact ? 'rose' : 'slate',
        };
    });

    const availabilityStatus = computed<VehicleEventStatus>(() => {
        const unavailable = toValue(event).impliesUnavailability;

        return {
            value: unavailable ? 'Indisponible' : 'Disponible',
            caption: unavailable
                ? 'Compté dans la heatmap et l\'utilisation'
                : 'Sans effet sur la disponibilité',
            tone: unavailable ? 'amber' : 'slate',
        };
    });

    return {
        deleteModalOpen,
        deleting,
        isOngoing,
        startLabel,
        endLabel,
        durationLabel,
        category,
        fiscalStatus,
        availabilityStatus,
        openDelete,
        closeDelete,
        confirmDelete,
    };
}
