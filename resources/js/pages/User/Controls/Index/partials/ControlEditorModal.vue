<script setup lang="ts">
/**
 * Inline editor (création / édition) d'un contrôle réglementaire global
 * (Chantier B / B1, domaine B). Organisé en sections claires : identité &
 * échéance, comportement, rappels (hérités ou surchargés), destinataires
 * (toujours prévenu, hérités retirables, propres au contrôle). Toute la logique
 * est dans {@link useControlDefinitionForm}.
 */
import { CalendarClock, Mail, Plus, SlidersHorizontal, Trash2, Users } from 'lucide-vue-next';
import { watch } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import CheckboxInput from '@/Components/Ui/CheckboxInput/CheckboxInput.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import NumberInput from '@/Components/Ui/NumberInput/NumberInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { useControlDefinitionForm } from '@/Composables/Control/Form/useControlDefinitionForm';

type ControlDefinition = App.Data.User.Control.ControlDefinitionData;
type ControlReminderSettings = App.Data.User.Control.ControlReminderSettingsData;
type EnumOption = App.Data.User.Vehicle.EnumOptionData;

const props = defineProps<{
    control: ControlDefinition | null;
    reminderSettings: ControlReminderSettings;
    anchorOptions: EnumOption[];
    durationUnitOptions: EnumOption[];
}>();

const open = defineModel<boolean>('open', { required: true });

const {
    form,
    isEditing,
    inheritedRecipients,
    isInheritedIncluded,
    toggleInherited,
    addOwnRecipient,
    removeOwnRecipient,
    fieldError,
    seed,
    submit,
} = useControlDefinitionForm(() => props.control, () => props.reminderSettings);

watch(open, (isOpen) => {
    if (isOpen) {
        seed();
    }
});

function onSubmit(): void {
    submit(() => {
        open.value = false;
    });
}
</script>

<template>
    <Modal
        v-model:open="open"
        size="xl"
        :title="isEditing ? 'Modifier le contrôle' : 'Nouveau contrôle réglementaire'"
        :close-on-backdrop="!form.processing"
    >
        <form class="flex flex-col gap-7" @submit.prevent="onSubmit">
            <!-- Identité & échéance -->
            <section class="flex flex-col gap-4">
                <div class="flex items-center gap-2">
                    <CalendarClock :size="16" :stroke-width="1.75" class="text-slate-400" />
                    <h3 class="text-sm font-semibold text-slate-900">Identité &amp; échéance</h3>
                </div>

                <TextInput
                    v-model="form.name"
                    label="Nom du contrôle"
                    placeholder="Contrôle technique"
                    required
                    :error="fieldError('name')"
                />

                <SelectInput
                    v-model="form.anchor"
                    label="Date de référence (ancre)"
                    hint="Point de départ du calcul de la première échéance."
                    :options="anchorOptions"
                    required
                    :error="fieldError('anchor')"
                />

                <div class="grid gap-4 sm:grid-cols-2">
                    <NumberInput
                        v-model="form.initial_duration_value"
                        label="Validité initiale"
                        :min="1"
                        required
                        :error="fieldError('initial_duration_value')"
                    />
                    <SelectInput
                        v-model="form.initial_duration_unit"
                        label="Unité"
                        :options="durationUnitOptions"
                        required
                        :error="fieldError('initial_duration_unit')"
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <NumberInput
                        v-model="form.cycle_value"
                        label="Périodicité ensuite"
                        :min="1"
                        required
                        :error="fieldError('cycle_value')"
                    />
                    <SelectInput
                        v-model="form.cycle_unit"
                        label="Unité"
                        :options="durationUnitOptions"
                        required
                        :error="fieldError('cycle_unit')"
                    />
                </div>
            </section>

            <!-- Comportement -->
            <section class="flex flex-col gap-4 border-t border-slate-100 pt-6">
                <div class="flex items-center gap-2">
                    <SlidersHorizontal :size="16" :stroke-width="1.75" class="text-slate-400" />
                    <h3 class="text-sm font-semibold text-slate-900">Comportement</h3>
                </div>

                <CheckboxInput
                    v-model="form.implies_unavailability"
                    label="Génère une indisponibilité du véhicule"
                    hint="Lorsque le contrôle est réalisé, un événement d'indisponibilité est créé sur le véhicule."
                />
                <CheckboxInput
                    v-model="form.notify_driver"
                    label="Prévenir le conducteur"
                    hint="Le conducteur du véhicule est ajouté aux destinataires des rappels (nécessite son email)."
                />
                <CheckboxInput
                    v-model="form.is_active"
                    label="Contrôle actif"
                    hint="Décocher pour suspendre ce contrôle sans le supprimer."
                />
            </section>

            <!-- Rappels -->
            <section class="flex flex-col gap-4 border-t border-slate-100 pt-6">
                <div class="flex items-center gap-2">
                    <Mail :size="16" :stroke-width="1.75" class="text-slate-400" />
                    <h3 class="text-sm font-semibold text-slate-900">Rappels</h3>
                </div>

                <CheckboxInput
                    v-model="form.customize_reminders"
                    label="Personnaliser le cycle de rappel pour ce contrôle"
                    hint="Sinon, les paramètres généraux des rappels s'appliquent."
                />

                <p
                    v-if="!form.customize_reminders"
                    class="rounded-lg bg-slate-50 px-3 py-2.5 text-xs text-slate-500"
                >
                    Paramètres généraux : premier rappel
                    {{ reminderSettings.daysBefore }} jour{{ reminderSettings.daysBefore > 1 ? 's' : '' }} avant
                    l'échéance<template v-if="reminderSettings.remindOnDueDay">, rappel le jour J</template>,
                    puis répétition tous les {{ reminderSettings.repeatEveryDays }}
                    jour{{ reminderSettings.repeatEveryDays > 1 ? 's' : '' }}.
                </p>

                <div v-else class="flex flex-col gap-4">
                    <NumberInput
                        v-model="form.reminder_days_before"
                        label="Premier rappel avant l'échéance"
                        :min="0"
                        :max="365"
                        :error="fieldError('reminder_days_before')"
                    >
                        <template #unit>jours</template>
                    </NumberInput>
                    <CheckboxInput
                        v-model="form.reminder_on_due_day"
                        label="Envoyer aussi un rappel le jour de l'échéance"
                        :error="fieldError('reminder_on_due_day')"
                    />
                    <NumberInput
                        v-model="form.reminder_repeat_every_days"
                        label="Puis répéter tous les"
                        :min="1"
                        :max="365"
                        :error="fieldError('reminder_repeat_every_days')"
                    >
                        <template #unit>jours</template>
                    </NumberInput>
                </div>
            </section>

            <!-- Destinataires -->
            <section class="flex flex-col gap-4 border-t border-slate-100 pt-6">
                <div class="flex items-center gap-2">
                    <Users :size="16" :stroke-width="1.75" class="text-slate-400" />
                    <h3 class="text-sm font-semibold text-slate-900">Destinataires</h3>
                </div>

                <div v-if="inheritedRecipients.length > 0" class="flex flex-col gap-2">
                    <p class="text-xs font-medium text-slate-500">
                        Hérités des paramètres généraux · décocher pour retirer de ce contrôle
                    </p>
                    <button
                        v-for="recipient in inheritedRecipients"
                        :key="recipient.email"
                        type="button"
                        class="group inline-flex items-center gap-2.5"
                        @click="toggleInherited(recipient.email)"
                    >
                        <span
                            :class="[
                                'flex size-4 shrink-0 items-center justify-center rounded-sm border transition-colors duration-[120ms]',
                                isInheritedIncluded(recipient.email)
                                    ? 'border-slate-900 bg-slate-900 text-white'
                                    : 'border-slate-300 bg-white',
                            ]"
                            aria-hidden="true"
                        >
                            <svg
                                v-if="isInheritedIncluded(recipient.email)"
                                viewBox="0 0 12 12"
                                class="size-3"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path d="M2.5 6.5l2.5 2.5 4.5-5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <span
                            :class="[
                                'text-sm',
                                isInheritedIncluded(recipient.email) ? 'text-slate-700' : 'text-slate-400 line-through',
                            ]"
                        >
                            {{ recipient.name }} · {{ recipient.email }}
                            <span v-if="recipient.isAlwaysNotify" class="text-slate-400">· prévenu par défaut</span>
                        </span>
                    </button>
                </div>

                <div class="flex flex-col gap-3">
                    <p class="text-xs font-medium text-slate-500">
                        Propres à ce contrôle
                    </p>
                    <p
                        v-if="form.own_recipients.length === 0"
                        class="text-sm text-slate-400 italic"
                    >
                        Aucun destinataire spécifique.
                    </p>
                    <ul v-else class="flex flex-col gap-3">
                        <li
                            v-for="(recipient, index) in form.own_recipients"
                            :key="index"
                            class="flex items-start gap-2"
                        >
                            <div class="grid flex-1 gap-3 sm:grid-cols-2">
                                <TextInput
                                    v-model="recipient.name"
                                    placeholder="Nom"
                                    :error="fieldError(`own_recipients.${index}.name`)"
                                />
                                <TextInput
                                    v-model="recipient.email"
                                    type="email"
                                    placeholder="email@exemple.fr"
                                    :error="fieldError(`own_recipients.${index}.email`)"
                                />
                            </div>
                            <button
                                type="button"
                                class="mt-1.5 flex size-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-400 transition-colors duration-[120ms] hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                                aria-label="Retirer ce destinataire"
                                @click="removeOwnRecipient(index)"
                            >
                                <Trash2 :size="16" :stroke-width="1.75" />
                            </button>
                        </li>
                    </ul>
                    <div>
                        <Button variant="secondary" size="sm" @click="addOwnRecipient">
                            <template #icon-left>
                                <Plus :size="14" :stroke-width="1.75" />
                            </template>
                            Ajouter un destinataire
                        </Button>
                    </div>
                </div>
            </section>
        </form>

        <template #footer>
            <Button variant="ghost" :disabled="form.processing" @click="open = false">
                Annuler
            </Button>
            <Button :loading="form.processing" @click="onSubmit">
                {{ isEditing ? 'Enregistrer' : 'Créer le contrôle' }}
            </Button>
        </template>
    </Modal>
</template>
