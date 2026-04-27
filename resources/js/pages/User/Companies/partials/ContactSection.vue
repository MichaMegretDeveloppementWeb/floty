<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import type { CompanyFormShape } from '@/pages/User/Companies/forms';

defineProps<{
    form: InertiaForm<CompanyFormShape>;
}>();
</script>

<template>
    <section class="flex flex-col gap-4">
        <p class="eyebrow">Contact</p>
        <TextInput
            v-model="form.contact_name"
            label="Nom du contact"
            :error="form.errors.contact_name"
        />
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <TextInput
                v-model="form.contact_email"
                type="email"
                label="E-mail"
                :error="form.errors.contact_email"
            />
            <TextInput
                v-model="form.contact_phone"
                type="tel"
                label="Téléphone"
                :error="form.errors.contact_phone"
            />
        </div>
    </section>
</template>
