<script setup lang="ts">
/**
 * Modale « Fait » (Chantier B / B2) : enregistre une exécution de contrôle
 * (date rétroactive autorisée, note facultative, 0..5 documents). Génère un
 * événement véhicule côté serveur. Logique dans useRecordExecutionForm.
 */
import { Paperclip, Trash2 } from 'lucide-vue-next';
import { watch } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import EventCategoriesField from '@/Components/Ui/EventCategoriesField/EventCategoriesField.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import NumberInput from '@/Components/Ui/NumberInput/NumberInput.vue';
import { useRecordExecutionForm } from '@/Composables/Control/Vehicle/useRecordExecutionForm';
import { vehicleEventCategorySuggestions } from '@/Utils/labels/vehicleEventEnumLabels';

type EffectiveControl = App.Data.User.Control.Vehicle.EffectiveControlData;

const props = defineProps<{
    vehicleId: number;
    control: EffectiveControl | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const { form, fieldError, addDocuments, removeDocument, seed, submit } = useRecordExecutionForm(props.vehicleId);

// « Contrôle » + « Entretien » sont ajoutés d'office par le backend ; l'utilisateur
// peut ajouter jusqu'à 3 catégories de plus (plafond de 5).
const lockedControlCategories = ['Contrôle', 'Entretien'];
const categorySuggestions = [...vehicleEventCategorySuggestions];

watch(open, (isOpen) => {
    if (isOpen && props.control !== null) {
        seed(props.control);
    }
});

function onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    addDocuments(input.files);
    input.value = '';
}

function onSubmit(): void {
    submit(() => {
        open.value = false;
    });
}
</script>

<template>
    <Modal
        v-model:open="open"
        size="md"
        :title="control ? `Marquer « ${control.name} » comme fait` : 'Marquer comme fait'"
        :close-on-backdrop="!form.processing"
    >
        <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
            <DateInput
                v-model="form.executed_on"
                label="Date de réalisation"
                hint="Vous pouvez saisir une date passée (rétroactive)."
                required
                :error="fieldError('executed_on')"
            />

            <div class="flex flex-col gap-1.5">
                <label for="control-execution-note" class="text-sm font-medium text-slate-700">
                    Note <span class="text-slate-400">(facultatif)</span>
                </label>
                <textarea
                    id="control-execution-note"
                    v-model="form.note"
                    rows="3"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-base leading-snug text-slate-900 transition-colors duration-[120ms] ease-out focus:border-slate-400 focus:shadow-[0_0_0_3px_var(--color-slate-100)] focus:outline-none"
                    placeholder="Observations, centre de contrôle, résultat..."
                />
                <InputError :message="fieldError('note')" />
            </div>

            <NumberInput
                v-model="form.amount"
                label="Coût"
                :min="0"
                :step="0.01"
                placeholder="0,00"
                hint="Coût du contrôle (TTC), facultatif."
                :error="fieldError('amount_cents')"
            >
                <template #unit>€</template>
            </NumberInput>

            <EventCategoriesField
                :model-value="form.categories"
                :locked-defaults="lockedControlCategories"
                :suggestions="categorySuggestions"
                :max="5"
                :error="fieldError('categories')"
                @update:model-value="(value: string[]) => (form.categories = value)"
            />

            <div class="flex flex-col gap-2">
                <span class="text-sm font-medium text-slate-700">
                    Documents <span class="text-slate-400">({{ form.documents.length }}/5)</span>
                </span>
                <label
                    class="inline-flex w-fit cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 transition-colors duration-[120ms] hover:border-slate-300 hover:bg-slate-50"
                >
                    <Paperclip :size="15" :stroke-width="1.75" />
                    Joindre un fichier
                    <input
                        type="file"
                        class="hidden"
                        accept=".jpg,.jpeg,.png,.webp,.pdf"
                        multiple
                        :disabled="form.documents.length >= 5"
                        @change="onFileChange"
                    />
                </label>
                <ul v-if="form.documents.length > 0" class="flex flex-col gap-1.5">
                    <li
                        v-for="(file, index) in form.documents"
                        :key="index"
                        class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-1.5 text-sm text-slate-700"
                    >
                        <span class="min-w-0 truncate">{{ file.name }}</span>
                        <button
                            type="button"
                            class="shrink-0 text-slate-400 transition-colors duration-[120ms] hover:text-rose-600"
                            aria-label="Retirer ce fichier"
                            @click="removeDocument(index)"
                        >
                            <Trash2 :size="15" :stroke-width="1.75" />
                        </button>
                    </li>
                </ul>
                <InputError :message="fieldError('documents')" />
            </div>
        </form>

        <template #footer>
            <Button variant="ghost" :disabled="form.processing" @click="open = false">Annuler</Button>
            <Button :loading="form.processing" @click="onSubmit">Enregistrer</Button>
        </template>
    </Modal>
</template>
