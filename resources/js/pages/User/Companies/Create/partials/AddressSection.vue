<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import type { CompanyFormShape } from '@/pages/User/Companies/Create/forms';

defineProps<{
    form: InertiaForm<CompanyFormShape>;
}>();
</script>

<template>
    <section class="flex flex-col gap-4">
        <p class="eyebrow">Adresse</p>
        <TextInput
            v-model="form.address_line_1"
            label="Adresse"
            :error="form.errors.address_line_1"
        />
        <TextInput
            v-model="form.address_line_2"
            label="Complément"
            :error="form.errors.address_line_2"
        />
        <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_2fr_1fr]">
            <TextInput
                v-model="form.postal_code"
                label="Code postal"
                :error="form.errors.postal_code"
            />
            <TextInput
                v-model="form.city"
                label="Ville"
                :error="form.errors.city"
            />
            <TextInput
                v-model="form.country"
                label="Pays (ISO)"
                hint="FR, BE, CH..."
                :error="form.errors.country"
                required
            />
        </div>
    </section>
</template>
