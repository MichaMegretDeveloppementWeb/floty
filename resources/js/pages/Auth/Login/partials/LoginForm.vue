<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Button from '@/Components/Ui/Button/Button.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { store as loginStoreRoute } from '@/routes/login';
import { request as forgotPasswordRoute } from '@/routes/password';

const form = useForm({
    email: '',
    password: '',
});

const submit = (): void => {
    form.post(loginStoreRoute.url(), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <form
        class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
        @submit.prevent="submit"
    >
        <TextInput
            v-model="form.email"
            type="email"
            label="Adresse e-mail"
            autocomplete="email"
            :error="form.errors.email"
            required
        />
        <TextInput
            v-model="form.password"
            type="password"
            label="Mot de passe"
            autocomplete="current-password"
            :error="form.errors.password"
            required
        />

        <Button type="submit" block :loading="form.processing">
            Se connecter
        </Button>

        <div class="text-center">
            <a
                :href="forgotPasswordRoute.url()"
                class="text-sm text-slate-500 underline-offset-2 hover:text-slate-700 hover:underline"
            >
                Mot de passe oublié ?
            </a>
        </div>
    </form>
</template>
