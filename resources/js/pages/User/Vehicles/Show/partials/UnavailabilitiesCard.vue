<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import { destroy as unavailabilitiesDestroyRoute } from '@/routes/user/unavailabilities';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { unavailabilityTypeLabel } from '@/Utils/labels/unavailabilityEnumLabels';
import UnavailabilityFormModal from './UnavailabilityFormModal.vue';

type Unavailability = App.Data.User.Unavailability.UnavailabilityData;

const props = defineProps<{
    vehicleId: number;
    unavailabilities: Unavailability[];
    /** Dates ISO Y-m-d déjà attribuées au véhicule (passées au modal). */
    busyDates: string[];
}>();

const formOpen = ref<boolean>(false);
const editing = ref<Unavailability | null>(null);

const confirmOpen = ref<boolean>(false);
const deleting = ref<Unavailability | null>(null);

const openCreate = (): void => {
    editing.value = null;
    formOpen.value = true;
};

const openEdit = (item: Unavailability): void => {
    editing.value = item;
    formOpen.value = true;
};

const askDelete = (item: Unavailability): void => {
    deleting.value = item;
    confirmOpen.value = true;
};

const confirmDelete = (): void => {
    if (!deleting.value) {
        return;
    }

    router.delete(
        unavailabilitiesDestroyRoute.url({ unavailability: deleting.value.id }),
        {
            preserveScroll: true,
            onFinish: () => {
                confirmOpen.value = false;
                deleting.value = null;
            },
        },
    );
};

const formatPeriod = (item: Unavailability): string => {
    const start = formatDateFr(item.startDate);

    if (item.endDate === null) {
        return `Depuis le ${start} (en cours)`;
    }

    const end = formatDateFr(item.endDate);

    return `Du ${start} au ${end}`;
};
</script>

<template>
    <Card>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">
                        Indisponibilités
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ props.unavailabilities.length }} période{{
                            props.unavailabilities.length > 1 ? 's' : ''
                        }}
                        enregistrée{{
                            props.unavailabilities.length > 1 ? 's' : ''
                        }}
                    </p>
                </div>
                <Button size="sm" @click="openCreate">
                    <template #icon-left>
                        <Plus :size="14" :stroke-width="1.75" />
                    </template>
                    Ajouter
                </Button>
            </div>
        </template>

        <p
            v-if="props.unavailabilities.length === 0"
            class="text-sm text-slate-500 italic"
        >
            Aucune indisponibilité enregistrée pour ce véhicule.
        </p>

        <ul v-else class="flex flex-col divide-y divide-slate-100">
            <li
                v-for="item in props.unavailabilities"
                :key="item.id"
                class="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"
            >
                <div class="flex flex-col gap-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge :tone="item.hasFiscalImpact ? 'rose' : 'slate'">
                            {{ unavailabilityTypeLabel[item.type] }}
                        </Badge>
                        <span class="text-sm font-medium text-slate-900">
                            {{ formatPeriod(item) }}
                        </span>
                        <span
                            v-if="item.daysCount > 0"
                            class="font-mono text-xs text-slate-500"
                        >
                            {{ item.daysCount }}j
                        </span>
                    </div>
                    <p
                        v-if="item.description"
                        class="text-sm whitespace-pre-line text-slate-600"
                    >
                        {{ item.description }}
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        aria-label="Modifier"
                        @click="openEdit(item)"
                    >
                        <template #icon-left>
                            <Pencil :size="14" :stroke-width="1.75" />
                        </template>
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        aria-label="Supprimer"
                        @click="askDelete(item)"
                    >
                        <template #icon-left>
                            <Trash2 :size="14" :stroke-width="1.75" />
                        </template>
                    </Button>
                </div>
            </li>
        </ul>

        <UnavailabilityFormModal
            v-model:open="formOpen"
            :vehicle-id="props.vehicleId"
            :editing="editing"
            :busy-dates="props.busyDates"
        />

        <ConfirmModal
            v-model:open="confirmOpen"
            title="Supprimer cette indisponibilité"
            message="Cette action est irréversible. La période sera retirée du calcul fiscal du véhicule."
            confirm-label="Supprimer"
            tone="danger"
            @confirm="confirmDelete"
        />
    </Card>
</template>
