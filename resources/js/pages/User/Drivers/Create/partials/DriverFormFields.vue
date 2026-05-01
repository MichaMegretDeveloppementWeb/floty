<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- pattern Inertia useForm partagé entre composables et composants partials */
import type { InertiaForm } from '@inertiajs/vue3';
import DateInput from '@/Components/Ui/DateInput/DateInput.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import type { CompanyOption, CreateFormShape } from '@/Composables/Driver/useDriverForm';

defineProps<{
    form: InertiaForm<CreateFormShape>;
    companies: CompanyOption[];
}>();
</script>

<template>
    <div class="grid gap-4">
        <div>
            <FieldLabel for="first_name">Prénom</FieldLabel>
            <TextInput id="first_name" v-model="form.first_name" />
            <InputError :message="form.errors.first_name" />
        </div>

        <div>
            <FieldLabel for="last_name">Nom</FieldLabel>
            <TextInput id="last_name" v-model="form.last_name" />
            <InputError :message="form.errors.last_name" />
        </div>

        <div>
            <FieldLabel for="initial_company_id">Entreprise initiale</FieldLabel>
            <SelectInput
                id="initial_company_id"
                v-model="form.initial_company_id"
                placeholder="Sélectionner une entreprise"
                :options="companies.map((c) => ({ value: c.id, label: `${c.shortCode} — ${c.legalName}` }))"
            />
            <InputError :message="form.errors.initial_company_id" />
        </div>

        <div>
            <FieldLabel for="initial_joined_at">Date d'entrée dans l'entreprise</FieldLabel>
            <DateInput id="initial_joined_at" v-model="form.initial_joined_at" />
            <InputError :message="form.errors.initial_joined_at" />
        </div>
    </div>
</template>
