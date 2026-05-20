<script setup lang="ts">
/**
 * Driver selector for the Contract form.
 * Disabled until companyId + startDate + endDate are all set.
 * Loads options active on the period and clears the value if it becomes invalid.
 */
import { computed, ref, watch } from 'vue';
import SearchableSelect from '@/Components/Ui/SearchableSelect/SearchableSelect.vue';
import { useApi } from '@/Composables/Shared/useApi';
import { contractOptions as optionsRoute } from '@/routes/user/drivers';

type DriverOption = App.Data.User.Driver.DriverOptionData;

const props = defineProps<{
    modelValue: number | null;
    companyId: number | null;
    startDate: string | null;
    endDate: string | null;
    /** Driver ids to exclude from the displayed options. */
    excludedIds?: number[];
    /** Auto-open the dropdown on mount, after the initial reload. */
    autoOpenOnMount?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const options = ref<DriverOption[]>([]);
const loading = ref(false);

const isReady = computed<boolean>(
    () =>
        props.companyId !== null &&
        props.companyId > 0 &&
        props.startDate !== null &&
        props.startDate !== '' &&
        props.endDate !== null &&
        props.endDate !== '',
);

const items = computed(() => {
    const excluded = new Set(props.excludedIds ?? []);

    return options.value
        // Always keep the currently selected value so its label stays visible.
        .filter((d) => d.id === props.modelValue || !excluded.has(d.id))
        .map((d) => ({ value: d.id, label: d.fullName }));
});

const valueModel = computed({
    get: () => props.modelValue,
    set: (v: string | number | null) => {
        emit('update:modelValue', typeof v === 'number' ? v : null);
    },
});

const api = useApi();

async function reload(): Promise<void> {
    if (!isReady.value) {
        options.value = [];

        return;
    }

    loading.value = true;

    try {
        const data = await api.get<{ drivers: DriverOption[] }>(
            optionsRoute.url(),
            {
                company_id: props.companyId!,
                start_date: props.startDate!,
                end_date: props.endDate!,
            },
        );
        options.value = data.drivers;

        // Clear the value if the previously selected driver is no longer available.
        if (
            props.modelValue !== null &&
            !data.drivers.some((d) => d.id === props.modelValue)
        ) {
            emit('update:modelValue', null);
        }
    } catch {
        // useApi already surfaced the error toast; fall back to empty options.
        options.value = [];
    } finally {
        loading.value = false;
    }
}

watch(
    () => [props.companyId, props.startDate, props.endDate],
    () => {
        reload();
    },
    { immediate: true },
);

</script>

<template>
    <SearchableSelect
        v-model="valueModel"
        :options="items"
        :placeholder="
            !isReady
                ? 'Sélectionner d\'abord une entreprise et des dates'
                : loading
                  ? 'Chargement…'
                  : items.length === 0
                    ? 'Aucun conducteur disponible sur cette période'
                    : 'Choisir un conducteur'
        "
        :disabled="!isReady || loading"
        :auto-open-on-mount="autoOpenOnMount"
    />
</template>
