<script setup lang="ts">
/**
 * Section repliable « + Plus d'options » du drawer planning
 * (chantier UX-Loc).
 *
 * Contient les champs **secondaires** d'une attribution :
 *   - Référence location
 *   - Notes
 *   - Bouton « 📎 Documents » (ouvre `ContractDocumentsModal`)
 *
 * Pliée par défaut : la majorité des attributions rapides depuis le
 * drawer ne renseignent pas ces champs. Permet de garder la surface
 * compacte sans sacrifier la parité fonctionnelle avec la page Create.
 */
import { ChevronDown, Paperclip } from 'lucide-vue-next';
import { ref } from 'vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';

const props = defineProps<{
    reference: string | null;
    notes: string | null;
    filesCount: number;
}>();

const emit = defineEmits<{
    'update:reference': [value: string | null];
    'update:notes': [value: string | null];
    'open-documents': [];
}>();

const isOpen = ref<boolean>(false);

function toggle(): void {
    isOpen.value = !isOpen.value;
}

function onReferenceChange(v: string): void {
    emit('update:reference', v === '' ? null : v);
}

function onNotesChange(event: Event): void {
    const target = event.target as HTMLTextAreaElement;
    emit('update:notes', target.value === '' ? null : target.value);
}
</script>

<template>
    <section class="flex flex-col gap-3">
        <button
            type="button"
            class="inline-flex items-center gap-1.5 self-start text-xs font-semibold tracking-wide text-slate-500 uppercase transition-colors duration-[120ms] ease-out hover:text-slate-700"
            :aria-expanded="isOpen"
            @click="toggle"
        >
            <ChevronDown
                :size="14"
                :stroke-width="1.75"
                :class="['transition-transform duration-[120ms] ease-out', isOpen ? 'rotate-0' : '-rotate-90']"
                aria-hidden="true"
            />
            Plus d'options
        </button>

        <div v-if="isOpen" class="flex flex-col gap-3">
            <div>
                <FieldLabel for="drawer_contract_reference">Référence</FieldLabel>
                <TextInput
                    id="drawer_contract_reference"
                    :model-value="props.reference ?? ''"
                    placeholder="Ex. : CTR-2024-001"
                    @update:model-value="onReferenceChange"
                />
            </div>

            <div>
                <FieldLabel for="drawer_notes">Notes</FieldLabel>
                <textarea
                    id="drawer_notes"
                    rows="2"
                    class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                    placeholder="Conditions particulières, contact, etc."
                    :value="props.notes ?? ''"
                    @input="onNotesChange"
                />
            </div>

            <button
                type="button"
                class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm transition-colors duration-[120ms] ease-out hover:border-slate-300 hover:bg-slate-50"
                @click="emit('open-documents')"
            >
                <span class="inline-flex items-center gap-2 text-slate-900">
                    <Paperclip :size="14" :stroke-width="1.75" aria-hidden="true" />
                    Documents
                </span>
                <span
                    :class="[
                        'rounded-md px-2 py-0.5 text-xs font-medium',
                        props.filesCount > 0
                            ? 'bg-blue-50 text-blue-700'
                            : 'text-slate-400',
                    ]"
                >
                    {{ props.filesCount > 0 ? `${props.filesCount} PDF` : 'Aucun' }}
                </span>
            </button>
        </div>
    </section>
</template>
