<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- pattern Inertia useForm partagé entre composables et composants partials */
import type { InertiaForm } from '@inertiajs/vue3';
import { Building2, IdCard } from 'lucide-vue-next';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import type {
    CompanyOption,
    CreateFormShape,
} from '@/Composables/Driver/useDriverForm';

defineProps<{
    form: InertiaForm<CreateFormShape>;
    companies: CompanyOption[];
}>();
</script>

<template>
    <div class="flex flex-col gap-8">
        <section
            class="flex flex-col gap-5 rounded-xl border border-slate-200 bg-white p-6 md:p-8"
        >
            <header class="flex items-start gap-3">
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700"
                >
                    <IdCard :size="18" :stroke-width="1.75" />
                </span>
                <div class="flex flex-col">
                    <h2 class="text-base font-semibold text-slate-900">
                        Identité
                    </h2>
                    <p class="text-sm text-slate-500">
                        Le nom complet apparaîtra sur les contrats et dans les
                        documents fiscaux.
                    </p>
                </div>
            </header>

            <div class="grid grid-cols-1 gap-x-5 gap-y-6 md:grid-cols-2">
                <TextInput
                    v-model="form.first_name"
                    label="Prénom"
                    :error="form.errors.first_name"
                    required
                />
                <TextInput
                    v-model="form.last_name"
                    label="Nom"
                    :error="form.errors.last_name"
                    required
                />
            </div>
        </section>

        <section
            class="flex flex-col gap-5 rounded-xl border border-slate-200 bg-white p-6 md:p-8"
        >
            <header class="flex items-start gap-3">
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700"
                >
                    <Building2 :size="18" :stroke-width="1.75" />
                </span>
                <div class="flex flex-col">
                    <h2 class="text-base font-semibold text-slate-900">
                        Première entreprise
                    </h2>
                    <p class="text-sm text-slate-500">
                        Au moins une entreprise est obligatoire à la création.
                        Vous pourrez en rattacher d'autres ensuite.
                    </p>
                </div>
            </header>

            <div class="grid grid-cols-1 gap-x-5 gap-y-6 md:grid-cols-2">
                <SelectInput
                    v-model="form.initial_company_id"
                    label="Entreprise"
                    placeholder="Sélectionner une entreprise"
                    :options="
                        companies.map((c) => ({
                            value: c.id,
                            label: `${c.shortCode} — ${c.legalName}`,
                        }))
                    "
                    :error="form.errors.initial_company_id"
                    required
                />
                <DateInput
                    v-model="form.initial_joined_at"
                    label="Date d'entrée"
                    :error="form.errors.initial_joined_at"
                    required
                />
            </div>
        </section>
    </div>
</template>
