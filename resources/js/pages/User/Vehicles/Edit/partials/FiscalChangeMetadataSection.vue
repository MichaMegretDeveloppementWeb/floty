<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { History } from 'lucide-vue-next';
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
        class="flex flex-col gap-5 rounded-xl border border-blue-200 bg-blue-50/40 p-6 md:p-8"
        aria-live="polite"
    >
        <header class="flex items-start gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                <History :size="18" :stroke-width="1.75" />
            </span>
            <div class="flex flex-col">
                <h2 class="text-base font-semibold text-slate-900">
                    Nouvelle version d'historique
                </h2>
                <p class="text-sm text-slate-600">
                    Vous avez modifié une caractéristique fiscale. Précisez la date d'effet et le motif.
                </p>
            </div>
        </header>

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
