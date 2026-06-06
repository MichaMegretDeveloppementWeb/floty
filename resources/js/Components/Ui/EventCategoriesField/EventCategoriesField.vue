<script setup lang="ts">
/**
 * Multi-category editor (Chantier A) shared by the event form and the control
 * "Fait" modal. Shows the fixed default(s) as locked chips, then a dynamic list
 * of free-text inputs, each with a real styled dropdown of suggestions
 * (backend distinct + seed) filtered by prefix as you type · any typed text is
 * still kept. Live dedup vs the locked defaults and other entries. The model is
 * the CUSTOM categories only · the backend prepends the defaults.
 */
import { Lock, Plus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import { duplicateCustomIndices, normalizeCategory } from '@/Utils/vehicleEventCategories';

const props = withDefaults(
    defineProps<{
        /** Custom categories (v-model). */
        modelValue: string[];
        /** Fixed default categories shown locked (non-removable). */
        lockedDefaults?: string[];
        /** Autocomplete suggestions (merged: backend distinct + seed). */
        suggestions?: string[];
        /** Total cap including the locked defaults. */
        max?: number;
        error?: string;
    }>(),
    {
        lockedDefaults: () => [],
        suggestions: () => [],
        max: 5,
    },
);

const emit = defineEmits<{ 'update:modelValue': [string[]] }>();

/** Index of the row whose suggestion dropdown is open (null = none). */
const openIndex = ref<number | null>(null);

const maxCustom = computed<number>(() => Math.max(0, props.max - props.lockedDefaults.length));

const remaining = computed<number>(() => maxCustom.value - props.modelValue.length);

const canAdd = computed<boolean>(() => {
    if (props.modelValue.length >= maxCustom.value) {
        return false;
    }

    const last = props.modelValue[props.modelValue.length - 1];

    // Force the previous input to be filled before adding another.
    return last === undefined || last.trim() !== '';
});

const duplicateIndices = computed<Set<number>>(() =>
    duplicateCustomIndices(props.modelValue, props.lockedDefaults),
);

/**
 * Suggestions for one row: the global list minus the locked defaults and the
 * OTHER rows' values, filtered by prefix on the current row's text.
 */
function suggestionsFor(index: number): string[] {
    const used = new Set(
        [...props.lockedDefaults, ...props.modelValue.filter((_, i) => i !== index)]
            .map(normalizeCategory)
            .filter((key) => key !== ''),
    );
    const typed = normalizeCategory(props.modelValue[index] ?? '');

    return props.suggestions.filter((suggestion) => {
        const key = normalizeCategory(suggestion);

        if (key === '' || used.has(key)) {
            return false;
        }

        return typed === '' || key.startsWith(typed);
    });
}

function updateAt(index: number, value: string): void {
    const next = [...props.modelValue];
    next[index] = value;
    emit('update:modelValue', next);
}

function removeAt(index: number): void {
    openIndex.value = null;
    emit('update:modelValue', props.modelValue.filter((_, i) => i !== index));
}

function selectSuggestion(index: number, value: string): void {
    updateAt(index, value);
    openIndex.value = null;
}

function add(): void {
    if (!canAdd.value) {
        return;
    }

    emit('update:modelValue', [...props.modelValue, '']);
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between gap-2">
            <label class="text-sm font-medium text-slate-500">
                Catégories
            </label>
            <span class="text-xs text-slate-400">
                {{ lockedDefaults.length + modelValue.length }} / {{ max }}
            </span>
        </div>

        <p class="text-xs text-slate-500">
            Texte libre, la liste propose les catégories existantes. Jusqu'à {{ max }} au total.
        </p>

        <div class="flex flex-col gap-2">
            <!-- Locked defaults -->
            <div
                v-for="locked in lockedDefaults"
                :key="`locked-${locked}`"
                class="flex items-center gap-2"
            >
                <Badge tone="slate" :uppercase="false">
                    {{ locked }}
                </Badge>
                <span
                    class="inline-flex items-center gap-1 text-[11px] text-slate-400"
                    title="Catégorie ajoutée automatiquement, non modifiable"
                >
                    <Lock :size="12" :stroke-width="1.75" aria-hidden="true" />
                    automatique
                </span>
            </div>

            <!-- Custom inputs with dropdown -->
            <div
                v-for="(_, index) in modelValue"
                :key="`custom-${index}`"
                class="flex flex-col gap-1"
            >
                <div class="flex items-start gap-2">
                    <div class="relative flex-1">
                        <input
                            :value="modelValue[index]"
                            maxlength="60"
                            autocomplete="off"
                            placeholder="Ex. Pneus, sinistre, marketing..."
                            :class="[
                                'w-full rounded-md border bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:ring-2 focus:ring-indigo-100 focus:outline-none',
                                duplicateIndices.has(index)
                                    ? 'border-rose-300 focus:border-rose-400'
                                    : 'border-slate-200 focus:border-indigo-400',
                            ]"
                            @focus="openIndex = index"
                            @click="openIndex = index"
                            @keydown.esc="openIndex = null"
                            @blur="openIndex = null"
                            @input="updateAt(index, ($event.target as HTMLInputElement).value)"
                        />
                        <ul
                            v-if="openIndex === index && suggestionsFor(index).length > 0"
                            class="absolute z-20 mt-1 max-h-48 w-full overflow-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg"
                        >
                            <li
                                v-for="suggestion in suggestionsFor(index)"
                                :key="suggestion"
                                class="cursor-pointer px-3 py-1.5 text-sm text-slate-700 transition-colors duration-[100ms] hover:bg-slate-50"
                                @mousedown.prevent="selectSuggestion(index, suggestion)"
                            >
                                {{ suggestion }}
                            </li>
                        </ul>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-9 w-8 shrink-0 cursor-pointer items-center justify-center rounded-md text-slate-500 transition-colors duration-[120ms] ease-out hover:bg-rose-100 hover:text-rose-700"
                        title="Retirer cette catégorie"
                        aria-label="Retirer cette catégorie"
                        @click="removeAt(index)"
                    >
                        <X :size="15" :stroke-width="1.75" />
                    </button>
                </div>
                <p v-if="duplicateIndices.has(index)" class="text-xs text-rose-600">
                    Cette catégorie est déjà présente.
                </p>
            </div>

            <button
                v-if="remaining > 0"
                type="button"
                :disabled="!canAdd"
                class="inline-flex w-fit cursor-pointer items-center gap-1.5 rounded-md border border-dashed border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors duration-[120ms] ease-out hover:border-slate-400 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                @click="add"
            >
                <Plus :size="14" :stroke-width="1.75" />
                Ajouter une catégorie
            </button>
        </div>

        <InputError v-if="error" :message="error" />
    </div>
</template>
