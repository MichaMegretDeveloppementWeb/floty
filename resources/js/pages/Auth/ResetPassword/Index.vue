<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FlotyMark from '@/Components/Brand/FlotyMark.vue';
import GuestLayout from '@/Components/Layouts/GuestLayout.vue';
import { login as loginRoute } from '@/routes';
import ResetPasswordForm from './partials/ResetPasswordForm.vue';

defineProps<{
    token: string;
    email: string;
}>();
</script>

<template>
    <Head title="Réinitialisation du mot de passe" />

    <GuestLayout>
        <div
            class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-12"
        >
            <div class="w-full max-w-sm">
                <header class="mb-8 flex flex-col items-center gap-3">
                    <FlotyMark :size="48" class="text-slate-900" />
                    <p class="eyebrow">Floty · Récupération</p>
                    <h1
                        class="text-3xl font-semibold tracking-tight text-slate-900"
                    >
                        Nouveau mot de passe
                    </h1>
                </header>

                <ResetPasswordForm :token="token" :email="email" />

                <p class="mt-6 text-center text-xs text-slate-400">
                    <a
                        :href="loginRoute.url()"
                        class="text-slate-500 underline-offset-2 hover:text-slate-700 hover:underline"
                    >
                        Retour à la connexion
                    </a>
                </p>
            </div>
        </div>
    </GuestLayout>
</template>
