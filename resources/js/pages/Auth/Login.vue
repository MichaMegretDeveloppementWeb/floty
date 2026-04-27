<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import FlotyMark from '@/Components/Brand/FlotyMark.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';

const form = useForm({
    email: '',
    password: '',
});

const submit = (): void => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Connexion" />

    <div
        class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-12"
    >
        <div class="w-full max-w-sm">
            <header class="mb-8 flex flex-col items-center gap-3">
                <FlotyMark :size="48" class="text-slate-900" />
                <p class="eyebrow">Floty · Flotte partagée</p>
                <h1
                    class="text-3xl font-semibold tracking-tight text-slate-900"
                >
                    Connexion
                </h1>
            </header>

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
            </form>

            <p class="mt-6 text-center text-xs text-slate-400">
                Accès réservé aux gestionnaires de flotte.
            </p>
        </div>
    </div>
</template>
