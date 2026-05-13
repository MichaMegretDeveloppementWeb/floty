<script setup lang="ts">
/**
 * Sélecteur conducteur pour le formulaire Contract (Phase 06 V1.2 - Q4).
 *
 * Disabled tant que `companyId + startDate + endDate` ne sont pas tous
 * renseignés. Charge les options actives sur la période exacte via
 * l'endpoint `/app/drivers/options`. Si la sélection courante devient
 * invalide après changement (company/dates), on émet `update:modelValue`
 * avec `null` pour la retirer du formulaire.
 */
import { computed, onMounted, ref, useTemplateRef, watch } from 'vue';
import SearchableSelect from '@/Components/Ui/SearchableSelect/SearchableSelect.vue';
import { contractOptions as optionsRoute } from '@/routes/user/drivers';

type DriverOption = App.Data.User.Driver.DriverOptionData;

const props = defineProps<{
    modelValue: number | null;
    companyId: number | null;
    startDate: string | null;
    endDate: string | null;
    /**
     * IDs de drivers à exclure des options affichées. Utilisé par le
     * multi-picker pour empêcher un même driver d'apparaître sur deux
     * lignes du même contrat (chantier #3 multi-conducteurs).
     */
    excludedIds?: number[];
    /**
     * Ouvre automatiquement la dropdown au mount (UX D5.10.Q). Utilisé
     * par `DriversMultiPicker` pour les lignes ajoutées dynamiquement ·
     * l'utilisateur n'a pas besoin de cliquer une 2e fois pour focuser.
     * Patient : attend la fin du `reload()` initial si nécessaire.
     */
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
        // On garde toujours la valeur sélectionnée même si elle est dans
        // `excludedIds` (sinon le label deviendrait vide), pour les
        // autres on filtre.
        .filter((d) => d.id === props.modelValue || !excluded.has(d.id))
        .map((d) => ({ value: d.id, label: d.fullName }));
});

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

// Ref sur le SearchableSelect interne pour ouvrir la dropdown
// programmatiquement au mount quand `autoOpenOnMount=true`.
const selectRef = useTemplateRef<{ open: () => void }>('selectRef');

function openWhenReady(): void {
    if (!loading.value && isReady.value) {
        selectRef.value?.open();

        return;
    }

    // Attend la fin du chargement initial avant d'ouvrir · l'utilisateur
    // n'a pas à cliquer une 2e fois après ajout d'une ligne. Le watcher
    // s'autodétruit après la première ouverture pour ne pas réouvrir
    // sur chaque reload ultérieur (changement period par exemple).
    const stop = watch(
        () => loading.value,
        (isLoading) => {
            if (!isLoading && isReady.value) {
                selectRef.value?.open();
                stop();
            }
        },
    );
}

onMounted(() => {
    if (props.autoOpenOnMount) {
        openWhenReady();
    }
});
</script>

<template>
    <SearchableSelect
        ref="selectRef"
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
