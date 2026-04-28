<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FlotyMark from '@/Components/Brand/FlotyMark.vue';
import LoginForm from './Partials/LoginForm.vue';
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

            <LoginForm />

            <p class="mt-6 text-center text-xs text-slate-400">
                Accès réservé aux gestionnaires de flotte.
            </p>
        </div>
    </div>
</template>
