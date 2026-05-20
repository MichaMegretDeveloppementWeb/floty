<script setup lang="ts">
/**
 * Multi-driver picker for the Contract form.
 * One DriverSelector row per selected driver plus "add" / "remove" controls.
 * Excludes already-picked drivers across rows. Emits number[] of ids.
 */
import { computed, ref, watch } from 'vue';
import DriverSelector from './DriverSelector.vue';

const props = defineProps<{
    modelValue: number[];
    companyId: number | null;
    startDate: string | null;
    endDate: string | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
}>();

// Each slot is a selected id or null when the row was just added.
const slots = ref<(number | null)[]>(props.modelValue.length > 0 ? [...props.modelValue] : []);

// Sync slots when the parent resets modelValue, ignoring no-op echo.
watch(
    () => props.modelValue,
    (next) => {
        const current = slots.value.filter((v): v is number => v !== null);
        const same = next.length === current.length && next.every((v, i) => v === current[i]);

        if (!same) {
            slots.value = [...next];
        }
    },
);

// Skip nulls when emitting to the parent (rows still being edited).
function emitChange(): void {
    const ids = slots.value.filter((v): v is number => v !== null);
    emit('update:modelValue', ids);
}

function selectorModel(index: number): { value: number | null; update: (v: number | null) => void } {
    return {
        value: slots.value[index] ?? null,
        update(v) {
            slots.value[index] = v;
            emitChange();
        },
    };
}

// Slots that should auto-open their dropdown on mount.
const autoOpenIndices = ref<Set<number>>(new Set());

function addSlot(): void {
    const newIndex = slots.value.length;
    autoOpenIndices.value.add(newIndex);
    slots.value.push(null);
}

function removeSlot(index: number): void {
    slots.value.splice(index, 1);
    emitChange();
}

// Exclude ids picked on other rows to prevent duplicate selection.
function excludedIdsForRow(index: number): number[] {
    return slots.value
        .map((v, i) => (i !== index && v !== null ? v : null))
        .filter((v): v is number => v !== null);
}

const canAddMore = computed<boolean>(() => {
    // Allow adding only when every existing row already has a value.
    if (slots.value.length === 0) {
        return true;
    }

    return slots.value.every((v) => v !== null);
});
</script>

<template>
    <div class="flex flex-col gap-2">
        <div
            v-for="(_, index) in slots"
            :key="index"
            class="flex items-start gap-2"
        >
            <div class="flex-1">
                <DriverSelector
                    :model-value="selectorModel(index).value"
                    :company-id="companyId"
                    :start-date="startDate"
                    :end-date="endDate"
                    :excluded-ids="excludedIdsForRow(index)"
                    :auto-open-on-mount="autoOpenIndices.has(index)"
                    @update:model-value="selectorModel(index).update"
                />
            </div>
            <button
                type="button"
                class="flex h-9 items-center justify-center rounded-md border border-slate-200 px-2 text-xs font-medium text-slate-600 hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700"
                aria-label="Retirer ce conducteur"
                @click="removeSlot(index)"
            >
                Retirer
            </button>
        </div>

        <div>
            <button
                type="button"
                :disabled="!canAddMore"
                class="inline-flex items-center gap-1 rounded-md border border-dashed border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                @click="addSlot"
            >
                + Ajouter un conducteur
            </button>
            <p
                v-if="slots.length === 0"
                class="mt-1 text-xs text-slate-500"
            >
                Aucun conducteur sélectionné. Cliquez pour en ajouter un (ou
                plusieurs) à cette location.
            </p>
        </div>
    </div>
</template>
