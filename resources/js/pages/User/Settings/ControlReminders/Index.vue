<script setup lang="ts">
/**
 * Domaine A · "Paramètres des rappels" (Chantier B / B1). Réglages généraux
 * appliqués par défaut à tous les contrôles réglementaires : cycle de rappel
 * par défaut, destinataire « toujours prévenu », et liste des destinataires
 * universels (niveau 0). Un contrôle global peut surcharger ces réglages
 * (domaine « Contrôles réglementaires »). Logique en composable.
 */
import { Head } from '@inertiajs/vue3';
import { BellRing, Plus, Trash2 } from 'lucide-vue-next';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import CheckboxInput from '@/Components/Ui/CheckboxInput/CheckboxInput.vue';
import NumberInput from '@/Components/Ui/NumberInput/NumberInput.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { useControlRemindersForm } from '@/Composables/Control/Settings/useControlRemindersForm';

const props = defineProps<{
    settings: App.Data.User.Control.ControlReminderSettingsData;
}>();

const { form, addRecipient, removeRecipient, fieldError, submit } = useControlRemindersForm(props.settings);
</script>

<template>
    <Head title="Paramètres · Rappels de contrôles" />

    <UserLayout>
        <div class="m-auto flex w-full max-w-[48em] flex-col gap-6">
            <header class="flex items-start gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <BellRing :size="22" :stroke-width="1.75" />
                </div>
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-medium tracking-wider text-slate-500 uppercase">
                        Paramètres
                    </p>
                    <h1 class="text-2xl font-semibold text-slate-900">
                        Rappels de contrôles
                    </h1>
                    <p class="text-sm text-slate-500">
                        Réglages par défaut des rappels d'échéance des contrôles réglementaires.
                        Chaque contrôle peut les conserver ou les surcharger depuis le
                        catalogue « Contrôles réglementaires ».
                    </p>
                </div>
            </header>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <Card>
                    <template #header>
                        <h2 class="text-base font-semibold text-slate-900">
                            Cycle de rappel par défaut
                        </h2>
                    </template>

                    <div class="flex flex-col gap-4">
                        <NumberInput
                            v-model="form.days_before"
                            label="Premier rappel avant l'échéance"
                            hint="Combien de jours avant la date d'échéance le premier rappel est envoyé."
                            :min="0"
                            :max="365"
                            :error="fieldError('days_before')"
                        >
                            <template #unit>jours</template>
                        </NumberInput>

                        <CheckboxInput
                            v-model="form.remind_on_due_day"
                            label="Envoyer aussi un rappel le jour de l'échéance"
                            :error="fieldError('remind_on_due_day')"
                        />

                        <NumberInput
                            v-model="form.repeat_every_days"
                            label="Puis répéter tous les"
                            hint="Après l'échéance, tant que le contrôle n'a pas été marqué comme fait."
                            :min="1"
                            :max="365"
                            :error="fieldError('repeat_every_days')"
                        >
                            <template #unit>jours</template>
                        </NumberInput>
                    </div>
                </Card>

                <Card>
                    <template #header>
                        <h2 class="text-base font-semibold text-slate-900">
                            Destinataire toujours prévenu
                        </h2>
                    </template>

                    <div class="flex flex-col gap-4">
                        <p class="text-sm text-slate-500">
                            Ce destinataire reçoit tous les rappels, sauf retrait explicite au niveau d'un contrôle.
                            Laisser vide pour ne pas en définir.
                        </p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <TextInput
                                v-model="form.always_notify_name"
                                label="Nom"
                                placeholder="Gestion de flotte"
                                :error="fieldError('always_notify_name')"
                            />
                            <TextInput
                                v-model="form.always_notify_email"
                                type="email"
                                label="Email"
                                placeholder="flotte@exemple.fr"
                                :error="fieldError('always_notify_email')"
                            />
                        </div>
                    </div>
                </Card>

                <Card>
                    <template #header>
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-base font-semibold text-slate-900">
                                Destinataires par défaut
                            </h2>
                            <span class="text-xs text-slate-500">
                                {{ form.default_recipients.length }}
                                destinataire{{ form.default_recipients.length > 1 ? 's' : '' }}
                            </span>
                        </div>
                    </template>

                    <div class="flex flex-col gap-4">
                        <p class="text-sm text-slate-500">
                            Destinataires inclus par défaut dans les rappels de tous les contrôles.
                        </p>

                        <p
                            v-if="form.default_recipients.length === 0"
                            class="rounded-lg border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500 italic"
                        >
                            Aucun destinataire par défaut.
                        </p>

                        <ul v-else class="flex flex-col gap-3">
                            <li
                                v-for="(recipient, index) in form.default_recipients"
                                :key="index"
                                class="flex items-start gap-2"
                            >
                                <div class="grid flex-1 gap-3 sm:grid-cols-2">
                                    <TextInput
                                        v-model="recipient.name"
                                        placeholder="Nom"
                                        :error="fieldError(`default_recipients.${index}.name`)"
                                    />
                                    <TextInput
                                        v-model="recipient.email"
                                        type="email"
                                        placeholder="email@exemple.fr"
                                        :error="fieldError(`default_recipients.${index}.email`)"
                                    />
                                </div>
                                <button
                                    type="button"
                                    class="mt-1.5 flex size-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-400 transition-colors duration-[120ms] hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                                    aria-label="Retirer ce destinataire"
                                    @click="removeRecipient(index)"
                                >
                                    <Trash2 :size="16" :stroke-width="1.75" />
                                </button>
                            </li>
                        </ul>

                        <div>
                            <Button variant="secondary" size="sm" @click="addRecipient">
                                <template #icon-left>
                                    <Plus :size="14" :stroke-width="1.75" />
                                </template>
                                Ajouter un destinataire
                            </Button>
                        </div>
                    </div>
                </Card>

                <div class="flex justify-end">
                    <Button :loading="form.processing" @click="submit">
                        Enregistrer
                    </Button>
                </div>
            </form>
        </div>
    </UserLayout>
</template>
