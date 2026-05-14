<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Button from '@/Components/Ui/Button/Button.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { email as passwordEmailRoute } from '@/routes/password';

const form = useForm({
    email: '',
});

const submit = (): void => {
    form.post(passwordEmailRoute().url, {
        onSuccess: () => form.reset('email'),
    });
};
</script>

<template>
    <form
        class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
        @submit.prevent="submit"
    >
        <p class="text-sm text-slate-600">
            Saisissez votre adresse e-mail. Si elle correspond à un
            compte, vous recevrez un lien de réinitialisation valide
            pendant 60 minutes.
        </p>

        <TextInput
            v-model="form.email"
            type="email"
            label="Adresse e-mail"
            autocomplete="email"
            :error="form.errors.email"
            required
        />

        <Button type="submit" block :loading="form.processing">
            Envoyer le lien de récupération
        </Button>
    </form>
</template>
