<script setup lang="ts">
import Button from '@/Components/Ui/Button/Button.vue';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import Modal from '@/Components/Ui/Modal/Modal.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { describeImpact } from '@/Composables/Vehicle/Show/computeVfcUpdateImpact';
import { useVfcEditForm } from '@/Composables/Vehicle/Show/useVfcEditForm';
import FiscalCharacteristicsSection from '@/pages/User/Vehicles/Edit/partials/FiscalCharacteristicsSection.vue';

type Vfc = App.Data.User.Vehicle.VehicleFiscalCharacteristicsData;

const props = defineProps<{
    editing: Vfc | null;
    history: ReadonlyArray<Vfc>;
    options: App.Data.User.Vehicle.VehicleFormOptionsData;
}>();

const open = defineModel<boolean>('open', { required: true });

const {
    form,
    changeReasonOptions,
    isOtherChange,
    isInitialCreation,
    canSubmit,
    nonDestructiveImpacts,
    destructiveImpacts,
    confirmationOpen,
    requestSubmit,
    confirmSubmit,
} = useVfcEditForm(props, open);
</script>

<template>
    <Modal
        v-model:open="open"
        title="Modifier la version fiscale"
        description="Édition libre des bornes et des champs fiscaux. Le moteur ajuste automatiquement les versions adjacentes en cas de chevauchement ou de trou."
        size="lg"
    >
        <form class="flex flex-col gap-5" @submit.prevent="requestSubmit">
            <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <DateInput
                    v-model="form.effective_from"
                    label="Date de début"
                    :error="form.errors.effective_from"
                    required
                />
                <DateInput
                    v-model="form.effective_to"
                    label="Date de fin"
                    hint="Laisser vide pour transformer cette version en version courante."
                    :error="form.errors.effective_to"
                />
            </section>

            <section
                v-if="nonDestructiveImpacts.length > 0"
                class="rounded-xl border border-blue-100 bg-blue-50/40 p-4"
                aria-live="polite"
            >
                <p class="text-xs font-medium tracking-wide text-blue-700 uppercase">
                    Ajustements automatiques prévus
                </p>
                <ul class="mt-2 flex flex-col gap-1 text-sm text-slate-700">
                    <li
                        v-for="(impact, idx) in nonDestructiveImpacts"
                        :key="`adj-${idx}`"
                    >
                        · {{ describeImpact(impact) }}
                    </li>
                </ul>
            </section>

            <section
                v-if="destructiveImpacts.length > 0"
                class="rounded-xl border border-rose-200 bg-rose-50/40 p-4"
                role="alert"
            >
                <p class="text-xs font-medium tracking-wide text-rose-700 uppercase">
                    Suppressions en cascade ({{ destructiveImpacts.length }})
                </p>
                <ul class="mt-2 flex flex-col gap-1 text-sm text-slate-700">
                    <li
                        v-for="(impact, idx) in destructiveImpacts"
                        :key="`del-${idx}`"
                    >
                        · {{ describeImpact(impact) }}
                    </li>
                </ul>
                <p class="mt-2 text-xs leading-snug text-rose-700">
                    Une confirmation explicite vous sera demandée avant
                    application.
                </p>
            </section>

            <FiscalCharacteristicsSection :form="form" :options="props.options" />

            <section
                v-if="isInitialCreation"
                class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 text-xs leading-snug text-slate-500"
            >
                Motif&nbsp;: <span class="font-medium text-slate-700">Création initiale</span>, non modifiable. Cette version est l'origine de l'historique fiscal du véhicule, elle ne décrit pas un changement.
            </section>

            <section
                v-else
                class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4"
            >
                <p class="eyebrow">Motif du changement</p>
                <SelectInput
                    v-model="form.change_reason"
                    label="Motif"
                    :options="changeReasonOptions"
                    :error="form.errors.change_reason"
                    required
                />
                <TextInput
                    v-if="isOtherChange"
                    v-model="form.change_note"
                    label="Note explicative"
                    hint="Précisez la nature du changement (motif « Autre changement »)."
                    :error="form.errors.change_note"
                    required
                />
            </section>
        </form>

        <template #footer>
            <Button
                variant="ghost"
                :disabled="form.processing"
                @click="open = false"
            >
                Annuler
            </Button>
            <Button
                :loading="form.processing"
                :disabled="!canSubmit"
                @click="requestSubmit"
            >
                Enregistrer
            </Button>
        </template>
    </Modal>

    <Modal
        v-model:open="confirmationOpen"
        title="Confirmer la suppression en cascade"
        :description="`Cette modification supprimera ${destructiveImpacts.length} ${destructiveImpacts.length === 1 ? 'autre version' : 'autres versions'} de l'historique fiscal.`"
        size="md"
    >
        <ul
            class="flex flex-col gap-1.5 rounded-xl border border-rose-200 bg-rose-50/40 p-4 text-sm text-slate-800"
        >
            <li
                v-for="(impact, idx) in destructiveImpacts"
                :key="`confirm-del-${idx}`"
            >
                · {{ describeImpact(impact) }}
            </li>
        </ul>
        <p
            v-if="nonDestructiveImpacts.length > 0"
            class="mt-3 text-xs leading-snug text-slate-500"
        >
            {{ nonDestructiveImpacts.length }}
            {{ nonDestructiveImpacts.length === 1 ? 'ajustement non destructif sera également appliqué' : 'ajustements non destructifs seront également appliqués' }}
            sur les voisines.
        </p>

        <template #footer>
            <Button
                variant="ghost"
                :disabled="form.processing"
                @click="confirmationOpen = false"
            >
                Annuler
            </Button>
            <Button
                variant="destructive"
                :loading="form.processing"
                @click="confirmSubmit"
            >
                Supprimer et appliquer
            </Button>
        </template>
    </Modal>
</template>
