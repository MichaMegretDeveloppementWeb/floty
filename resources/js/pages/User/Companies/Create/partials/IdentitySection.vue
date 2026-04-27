<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import type { CompanyFormShape } from '@/pages/User/Companies/Create/forms';

defineProps<{
    form: InertiaForm<CompanyFormShape>;
    colorOptions: App.Data.User.Company.CompanyColorOptionData[];
}>();
</script>

<template>
    <section class="flex flex-col gap-4">
        <p class="eyebrow">Identité</p>
        <TextInput
            v-model="form.legal_name"
            label="Raison sociale"
            :error="form.errors.legal_name"
            required
        />
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <TextInput
                v-model="form.short_code"
                label="Code court"
                hint="2 à 5 caractères. Sert d'étiquette courte dans le planning."
                :error="form.errors.short_code"
                required
            />
            <SelectInput
                v-model="form.color"
                label="Couleur"
                :options="colorOptions"
                :error="form.errors.color"
                required
            />
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <TextInput
                v-model="form.siren"
                label="SIREN"
                hint="9 chiffres."
                mono
                :error="form.errors.siren"
            />
            <TextInput
                v-model="form.siret"
                label="SIRET"
                hint="14 chiffres (optionnel)."
                mono
                :error="form.errors.siret"
            />
        </div>
    </section>
</template>
