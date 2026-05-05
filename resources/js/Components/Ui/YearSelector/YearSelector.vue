<script setup lang="ts">
import { computed } from 'vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';

/**
 * Composant présentationnel pur pour sélectionner une année dans un
 * range donné. Wrapper léger autour de {@link SelectInput} avec
 * conversion type-safe des années en options.
 *
 * **Usage typique** : couplé au composable
 * {@link useYearScope} qui pilote la logique de sélection (URL sync,
 * reload Inertia, validation). Ce composant n'a aucune logique métier
 * — il se contente de rendre les années en select.
 *
 * **Mono-année** : si `availableYears` ne contient qu'une seule entrée,
 * le sélecteur est automatiquement désactivé (rien à choisir).
 *
 * **Type modelValue** : `number` strict (l'année ne peut pas être null
 * dans le scope). La conversion vers `string | number | null` attendu
 * par SelectInput est gérée localement.
 */

const props = defineProps<{
    /** Liste des années sélectionnables (range continu typiquement). */
    availableYears: readonly number[];
    /** Libellé optionnel au-dessus du select. */
    label?: string;
    /** Désactive explicitement (en plus du mono-année auto). */
    disabled?: boolean;
    /** ID HTML — auto-généré si omis. */
    id?: string;
}>();

const modelValue = defineModel<number>({ required: true });

const options = computed(() =>
    props.availableYears.map((year) => ({
        value: year,
        label: String(year),
    })),
);

const isMonoYear = computed<boolean>(() => props.availableYears.length <= 1);

// Proxy pour adapter le type strict `number` au type `string | number | null`
// attendu par SelectInput. La validation upstream garantit qu'on ne
// reçoit jamais autre chose qu'un number.
const proxiedValue = computed<string | number | null>({
    get: () => modelValue.value,
    set: (value) => {
        modelValue.value = Number(value);
    },
});
</script>

<template>
    <SelectInput
        :id="id"
        v-model="proxiedValue"
        :options="options"
        :label="label"
        :disabled="disabled || isMonoYear"
    />
</template>
