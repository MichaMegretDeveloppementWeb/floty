<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import type { VehicleEditFormShape } from '@/pages/User/Vehicles/Edit/forms';

type SelectOption = { value: string; label: string };

defineProps<{
    form: InertiaForm<VehicleEditFormShape>;
    changeReasonOptions: SelectOption[];
    isNewVersionMode: boolean;
    isOtherChange: boolean;
    hasFiscalChanges: boolean;
}>();
</script>

<template>
    <section class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4">
        <p class="eyebrow">Type de modification fiscale</p>

        <fieldset class="flex flex-col gap-2">
            <legend class="sr-only">Type de modification</legend>

            <label class="flex cursor-pointer items-start gap-3 rounded-lg bg-white px-3 py-2.5">
                <input
                    v-model="form.fiscal_change_mode"
                    type="radio"
                    name="fiscal_change_mode"
                    value="new_version"
                    class="mt-1"
                />
                <span class="flex flex-col gap-0.5">
                    <span class="text-sm font-medium text-slate-900">
                        Nouvelle version (changement réel)
                    </span>
                    <span class="text-xs text-slate-500">
                        Le véhicule a réellement changé (conversion, reclassement…). On clôture la version actuelle et on en crée une nouvelle à partir de la date d'effet.
                    </span>
                </span>
            </label>

            <label class="flex cursor-pointer items-start gap-3 rounded-lg bg-white px-3 py-2.5">
                <input
                    v-model="form.fiscal_change_mode"
                    type="radio"
                    name="fiscal_change_mode"
                    value="correction"
                    class="mt-1"
                />
                <span class="flex flex-col gap-0.5">
                    <span class="text-sm font-medium text-slate-900">
                        Correction de la version courante
                    </span>
                    <span class="text-xs text-slate-500">
                        Erreur de saisie initiale à corriger. La version courante est mise à jour en place, sans nouvelle ligne d'historique.
                    </span>
                </span>
            </label>
        </fieldset>

        <div v-if="isNewVersionMode" class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <DateInput
                v-model="form.effective_from"
                label="Date d'effet"
                hint="Par défaut aujourd'hui. Une date dans le passé peut écraser des versions historiques."
                :error="form.errors.effective_from"
                required
            />
            <SelectInput
                v-model="form.change_reason"
                label="Motif"
                :options="changeReasonOptions"
                :error="form.errors.change_reason"
                required
            />
        </div>

        <TextInput
            v-if="isNewVersionMode && isOtherChange"
            v-model="form.change_note"
            label="Note explicative"
            hint="Précisez la nature du changement (motif « Autre changement »)."
            :error="form.errors.change_note"
            required
        />

        <p
            v-if="isNewVersionMode && !hasFiscalChanges"
            class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800"
        >
            Modifiez au moins une caractéristique fiscale pour enregistrer une
            nouvelle version. Sinon, choisissez « Correction de la version
            courante » si l'objectif est seulement de mettre à jour l'identité.
        </p>
    </section>
</template>
