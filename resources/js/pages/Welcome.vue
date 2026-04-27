<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FlotyMark from '@/Components/Brand/FlotyMark.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import { login as loginRoute } from '@/routes';
import { dashboard as dashboardRoute } from '@/routes/user';

const page = usePage();
const isAuthenticated = computed(() => page.props.auth?.user !== null);
</script>

<template>
    <Head title="Floty — gestion de flotte partagée" />

    <main
        class="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-6 py-12"
    >
        <div class="flex max-w-md flex-col items-center gap-6 text-center">
            <FlotyMark :size="56" class="text-slate-900" />

            <div class="flex flex-col gap-2">
                <p class="eyebrow">Flotte partagée</p>
                <h1
                    class="text-3xl font-semibold tracking-tight text-slate-900 md:text-4xl"
                >
                    Floty
                </h1>
            </div>

            <p class="text-base leading-relaxed text-slate-600">
                Application de gestion d'une flotte de véhicules mutualisée
                entre plusieurs entreprises utilisatrices, avec calcul
                automatique des taxes CO₂ et polluants au prorata d'utilisation.
            </p>

            <div class="flex flex-col gap-2">
                <Link v-if="isAuthenticated" :href="dashboardRoute.url()">
                    <Button>Accéder au tableau de bord</Button>
                </Link>
                <Link v-else :href="loginRoute.url()">
                    <Button>Se connecter</Button>
                </Link>
            </div>

            <p class="mt-4 text-xs text-slate-400">
                Accès réservé aux gestionnaires de flotte.
            </p>
        </div>
    </main>
</template>
