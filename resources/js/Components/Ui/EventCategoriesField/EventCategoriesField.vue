<script setup lang="ts">
/**
 * Nature editor (refonte type → nature) shared by the event form and the
 * control "Fait" modal. Shows the fixed auto natures as locked chips, then a
 * dynamic unlimited list of free-text inputs, each with a styled dropdown of
 * catalogue suggestions split in two blocks (« Réducteur fiscal » first, then
 * the rest), filtered by prefix as you type · any typed text is still kept.
 * Live dedup vs the locked defaults and other entries. The model is the
 * user-supplied natures only · the backend prepends the auto ones.
 */
import { Lock, Plus, TrendingDown, X } from 'lucide-vue-next';
import type { ComponentPublicInstance } from 'vue';
import { computed, nextTick, ref } from 'vue';
import Badge from '@/Components/Ui/Badge/Badge.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import { duplicateCustomIndices, normalizeCategory } from '@/Utils/vehicleEventCategories';

type SuggestionBlock = {
    key: 'reductive' | 'other';
    label: string;
    items: string[];
};

const props = withDefaults(
    defineProps<{
        /** User-supplied natures (v-model). */
        modelValue: string[];
        /** Fixed auto natures shown locked (non-removable). */
        lockedDefaults?: string[];
        /** Catalogue suggestions · frozen fiscally-reductive block. */
        reductiveSuggestions?: string[];
        /** Catalogue suggestions · every other nature (base + user additions). */
        otherSuggestions?: string[];
        /** Mark the field as required (form context). */
        required?: boolean;
        error?: string;
    }>(),
    {
        lockedDefaults: () => [],
        reductiveSuggestions: () => [],
        otherSuggestions: () => [],
        required: false,
    },
);

const emit = defineEmits<{ 'update:modelValue': [string[]] }>();

/** Index of the row whose suggestion dropdown is open (null = none). */
const openIndex = ref<number | null>(null);

/** Live refs to the nature inputs, to focus the freshly added one. */
const inputEls = ref<(HTMLInputElement | null)[]>([]);

function registerInput(el: Element | ComponentPublicInstance | null, index: number): void {
    inputEls.value[index] = el instanceof HTMLInputElement ? el : null;
}

const canAdd = computed<boolean>(() => {
    const last = props.modelValue[props.modelValue.length - 1];

    // Force the previous input to be filled before adding another.
    return last === undefined || last.trim() !== '';
});

const duplicateIndices = computed<Set<number>>(() =>
    duplicateCustomIndices(props.modelValue, props.lockedDefaults),
);

/**
 * Suggestion blocks for one row: each block minus the locked defaults and the
 * OTHER rows' values, filtered by prefix on the current row's text. Empty
 * blocks are dropped.
 */
function suggestionBlocksFor(index: number): SuggestionBlock[] {
    const used = new Set(
        [...props.lockedDefaults, ...props.modelValue.filter((_, i) => i !== index)]
            .map(normalizeCategory)
            .filter((key) => key !== ''),
    );
    const typed = normalizeCategory(props.modelValue[index] ?? '');

    const filterBlock = (items: string[]): string[] =>
        items.filter((suggestion) => {
            const key = normalizeCategory(suggestion);

            if (key === '' || used.has(key)) {
                return false;
            }

            return typed === '' || key.startsWith(typed);
        });

    const blocks: SuggestionBlock[] = [
        { key: 'reductive', label: 'Réducteur fiscal', items: filterBlock(props.reductiveSuggestions) },
        { key: 'other', label: 'Autres natures', items: filterBlock(props.otherSuggestions) },
    ];

    return blocks.filter((block) => block.items.length > 0);
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

    const newIndex = props.modelValue.length;
    emit('update:modelValue', [...props.modelValue, '']);

    // Focus the new input once rendered; its @focus opens the suggestion list,
    // so the user lands directly in a ready-to-type field with suggestions.
    void nextTick(() => {
        inputEls.value[newIndex]?.focus();
    });
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <label class="text-sm font-medium text-slate-500">
            Nature
            <span v-if="required" aria-hidden="true" class="ml-0.5 text-rose-600">*</span>
        </label>

        <p class="text-xs text-slate-500">
            Texte libre, la liste propose les natures connues. Une nature
            « réducteur fiscal » rend l'événement fiscalement réducteur.
        </p>

        <div class="flex flex-col gap-2">
            <!-- Locked auto natures -->
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
                    title="Nature ajoutée automatiquement, non modifiable"
                >
                    <Lock :size="12" :stroke-width="1.75" aria-hidden="true" />
                    automatique
                </span>
            </div>

            <!-- Free inputs with grouped dropdown -->
            <div
                v-for="(_, index) in modelValue"
                :key="`custom-${index}`"
                class="flex flex-col gap-1"
            >
                <div class="flex items-start gap-2">
                    <div class="relative flex-1">
                        <input
                            :ref="(el) => registerInput(el, index)"
                            :value="modelValue[index]"
                            maxlength="60"
                            autocomplete="off"
                            placeholder="Ex. Maintenance / entretien, Vol..."
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
                        <div
                            v-if="openIndex === index && suggestionBlocksFor(index).length > 0"
                            class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg"
                        >
                            <template
                                v-for="block in suggestionBlocksFor(index)"
                                :key="block.key"
                            >
                                <p class="px-3 pt-1.5 pb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">
                                    {{ block.label }}
                                </p>
                                <ul>
                                    <li
                                        v-for="suggestion in block.items"
                                        :key="suggestion"
                                        class="flex cursor-pointer items-center gap-1.5 px-3 py-1.5 text-sm text-slate-700 transition-colors duration-[100ms] hover:bg-slate-50"
                                        @mousedown.prevent="selectSuggestion(index, suggestion)"
                                    >
                                        <TrendingDown
                                            v-if="block.key === 'reductive'"
                                            :size="13"
                                            :stroke-width="1.75"
                                            class="shrink-0 text-rose-500"
                                            aria-hidden="true"
                                        />
                                        {{ suggestion }}
                                    </li>
                                </ul>
                            </template>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-9 w-8 shrink-0 cursor-pointer items-center justify-center rounded-md text-slate-500 transition-colors duration-[120ms] ease-out hover:bg-rose-100 hover:text-rose-700"
                        title="Retirer cette nature"
                        aria-label="Retirer cette nature"
                        @click="removeAt(index)"
                    >
                        <X :size="15" :stroke-width="1.75" />
                    </button>
                </div>
                <p v-if="duplicateIndices.has(index)" class="text-xs text-rose-600">
                    Cette nature est déjà présente.
                </p>
            </div>

            <button
                type="button"
                :disabled="!canAdd"
                class="inline-flex w-fit cursor-pointer items-center gap-1.5 rounded-md border border-dashed border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors duration-[120ms] ease-out hover:border-slate-400 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                @click="add"
            >
                <Plus :size="14" :stroke-width="1.75" />
                Ajouter une nature
            </button>
        </div>

        <InputError v-if="error" :message="error" />
    </div>
</template>
