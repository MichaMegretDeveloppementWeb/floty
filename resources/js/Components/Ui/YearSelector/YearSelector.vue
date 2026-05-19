<script setup lang="ts">
/**
 * Presentational wrapper around `SelectInput` for picking a year from a
 * given range. Auto-disables when only one year is available. Selection
 * logic lives in the consuming composable (URL sync, reload, validation).
 */
import { computed } from 'vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';

const props = defineProps<{
    /** Selectable years (typically a continuous range). */
    availableYears: readonly number[];
    /** Optional label above the select. */
    label?: string;
    /** Explicit disable, on top of the auto mono-year disable. */
    disabled?: boolean;
    /** HTML id, auto-generated when omitted. */
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

// Adapter the strict `number` model to the `string | number | null`
// signature expected by SelectInput.
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
