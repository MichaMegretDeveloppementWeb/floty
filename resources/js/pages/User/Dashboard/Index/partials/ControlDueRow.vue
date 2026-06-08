<script setup lang="ts">
/** Dashboard control-due row pointing to the vehicle's « Contrôles » tab. */
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import { useControlLabels } from '@/Composables/Control/useControlLabels';
import { show as vehiclesShowRoute } from '@/routes/user/vehicles';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type Item = App.Data.User.Dashboard.DashboardControlsDueItemData;

const props = defineProps<{ item: Item }>();

// Only the static schedule-status maps are needed here; the anchor / unit option
// lists the composable also exposes are unused, hence the empty inputs.
const { scheduleStatusLabel, scheduleStatusTone, scheduleStatusIcon } = useControlLabels(
    () => [],
    () => [],
);

const targetUrl = computed<string>(
    () => vehiclesShowRoute(props.item.vehicleId, { query: { tab: 'controls' } }).url,
);

function open(): void {
    router.visit(targetUrl.value);
}
</script>

<template>
    <button
        type="button"
        class="group flex w-full cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-left transition-all duration-[120ms] ease-out hover:border-slate-300 hover:bg-slate-50"
        @click="open"
    >
        <div class="flex min-w-0 flex-1 flex-col gap-0.5">
            <p class="truncate text-sm font-medium text-slate-900">
                <span class="font-mono">{{ item.licensePlate }}</span>
                · {{ item.controlName }}
            </p>
            <p class="truncate text-xs text-slate-500">
                Échéance : {{ formatDateFr(item.nextDueDate) }}
            </p>
        </div>

        <Badge :tone="scheduleStatusTone(item.scheduleStatus)" :uppercase="false" class="shrink-0">
            <component
                :is="scheduleStatusIcon(item.scheduleStatus)"
                :size="12"
                :stroke-width="2"
                class="mr-1"
                aria-hidden="true"
            />
            {{ scheduleStatusLabel(item.scheduleStatus) }}
        </Badge>

        <span class="shrink-0 text-xs font-medium text-slate-600 group-hover:text-slate-900">
            Voir →
        </span>
    </button>
</template>
