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
    isOtherChange: boolean;
    hasFiscalChanges: boolean;
}>();
</script>

<template>
    <section
        class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-slate-50/50 p-4"
    >
        <p class="eyebrow">Métadonnées de la nouvelle version</p>
        <p class="text-xs leading-snug text-slate-500">
            Edit ne sert qu'aux changements réels du véhicule (conversion E85,
            reclassement N1→M1, retrofit…) qui justifient l'INSERT d'une nouvelle
            ligne d'historique. Pour corriger une saisie sur une version
            existante, utilisez plutôt le bouton « Modifier » dans la modale
            Historique de la page véhicule.
        </p>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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
            v-if="isOtherChange"
            v-model="form.change_note"
            label="Note explicative"
            hint="Précisez la nature du changement (motif « Autre changement »)."
            :error="form.errors.change_note"
            required
        />

        <p
            v-if="!hasFiscalChanges"
            class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800"
        >
            Modifiez au moins une caractéristique fiscale pour enregistrer
            une nouvelle version.
        </p>
    </section>
</template>
