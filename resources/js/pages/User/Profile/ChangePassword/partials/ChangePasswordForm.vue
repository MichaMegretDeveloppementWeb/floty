<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Button from '@/Components/Ui/Button/Button.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { update as changePasswordRoute } from '@/routes/profile/change-password';

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submit = (): void => {
    form.post(changePasswordRoute.url(), {
        onSuccess: () => form.reset('current_password', 'password', 'password_confirmation'),
        onError: () => form.reset('current_password'),
    });
};
</script>

<template>
    <form
        class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
        @submit.prevent="submit"
    >
        <TextInput
            v-model="form.current_password"
            type="password"
            label="Mot de passe actuel"
            autocomplete="current-password"
            :error="form.errors.current_password"
            required
        />

        <hr class="border-slate-100" />

        <TextInput
            v-model="form.password"
            type="password"
            label="Nouveau mot de passe"
            autocomplete="new-password"
            hint="8 caractères minimum, différent du mot de passe actuel."
            :error="form.errors.password"
            required
        />

        <TextInput
            v-model="form.password_confirmation"
            type="password"
            label="Confirmation du nouveau mot de passe"
            autocomplete="new-password"
            required
        />

        <Button type="submit" block :loading="form.processing">
            Mettre à jour le mot de passe
        </Button>
    </form>
</template>
