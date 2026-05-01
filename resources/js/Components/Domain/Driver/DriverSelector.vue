<script setup lang="ts">
/**
 * Sélecteur conducteur pour le formulaire Contract (Phase 06 V1.2 — Q4).
 *
 * Disabled tant que `companyId + startDate + endDate` ne sont pas tous
 * renseignés. Charge les options actives sur la période exacte via
 * l'endpoint `/app/drivers/options`. Si la sélection courante devient
 * invalide après changement (company/dates), on émet `update:modelValue`
 * avec `null` pour la retirer du formulaire.
 */
import { computed, ref, watch } from 'vue';
import SearchableSelect from '@/Components/Ui/SearchableSelect/SearchableSelect.vue';
import { contractOptions as optionsRoute } from '@/routes/user/drivers';

type DriverOption = App.Data.User.Driver.DriverOptionData;

const props = defineProps<{
    modelValue: number | null;
    companyId: number | null;
    startDate: string | null;
    endDate: string | null;
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

const items = computed(() =>
    options.value.map((d) => ({ value: d.id, label: d.fullName })),
);

const valueModel = computed({
    get: () => props.modelValue,
    set: (v: string | number | null) => {
        emit('update:modelValue', typeof v === 'number' ? v : null);
    },
});

async function reload(): Promise<void> {
    if (!isReady.value) {
        options.value = [];

        return;
    }

    loading.value = true;

    try {
        const url = `${optionsRoute().url}?company_id=${props.companyId}&start_date=${props.startDate}&end_date=${props.endDate}`;
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            options.value = [];

            return;
        }

        const data = (await response.json()) as { drivers: DriverOption[] };
        options.value = data.drivers;

        // Si le driver actuellement sélectionné n'est plus dans la liste, on le retire.
        if (
            props.modelValue !== null &&
            !data.drivers.some((d) => d.id === props.modelValue)
        ) {
            emit('update:modelValue', null);
        }
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
    />
</template>
