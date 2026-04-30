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
}>();
</script>

<template>
    <section
        class="flex flex-col gap-4 rounded-xl border border-blue-200 bg-blue-50/40 p-4"
        aria-live="polite"
    >
        <p class="eyebrow text-blue-700">Métadonnées de la nouvelle version</p>
        <p class="text-xs leading-snug text-slate-600">
            Vous avez modifié au moins une caractéristique fiscale. Une
            nouvelle version sera ajoutée à l'historique — précisez à
            quelle date elle prend effet et le motif du changement.
        </p>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <DateInput
                v-model="form.effective_from"
                label="Date d'effet"
                hint="Par défaut aujourd'hui. Une date passée peut remplacer des versions précédentes."
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
    </section>
</template>
