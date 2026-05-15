<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Button from '@/Components/Ui/Button/Button.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { update as passwordUpdateRoute } from '@/routes/password';

const props = defineProps<{
    token: string;
    email: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = (): void => {
    form.post(passwordUpdateRoute.url(), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <form
        class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
        @submit.prevent="submit"
    >
        <p class="text-sm text-slate-600">
            Choisissez un nouveau mot de passe (8 caractères minimum).
        </p>

        <TextInput
            v-model="form.email"
            type="email"
            label="Adresse e-mail"
            autocomplete="email"
            :error="form.errors.email"
            required
            readonly
        />

        <TextInput
            v-model="form.password"
            type="password"
            label="Nouveau mot de passe"
            autocomplete="new-password"
            :error="form.errors.password"
            required
        />

        <TextInput
            v-model="form.password_confirmation"
            type="password"
            label="Confirmation du mot de passe"
            autocomplete="new-password"
            required
        />

        <Button type="submit" block :loading="form.processing">
            Réinitialiser
        </Button>
    </form>
</template>
