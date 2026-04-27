<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import FlotyMark from '@/Components/Brand/FlotyMark.vue';
import { home as homeRoute } from '@/routes';

type ErrorContent = { title: string; detail: string };

const props = defineProps<{
    status: number;
}>();

const messages: Record<number, ErrorContent> = {
    404: {
        title: 'Page introuvable',
        detail: "Cette page n'existe pas ou a été déplacée. Si vous êtes arrivé ici depuis un lien Floty, contactez le support.",
    },
    500: {
        title: 'Erreur serveur',
        detail: "Une erreur inattendue est survenue. Nos équipes ont été notifiées. Veuillez réessayer dans quelques instants ; si le problème persiste, contactez le support.",
    },
    503: {
        title: 'Maintenance en cours',
        detail: 'Floty est temporairement indisponible pour maintenance. Réessayez dans quelques minutes.',
    },
};

const content = computed<ErrorContent>(() => messages[props.status] ?? messages[500]!);
</script>

<template>
    <Head :title="`${status} · ${content.title}`" />

    <main
        class="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-6 py-12"
    >
        <div class="flex max-w-md flex-col items-center gap-6 text-center">
            <FlotyMark :size="48" class="text-slate-900" />

            <p class="font-mono text-6xl font-semibold tracking-tight text-slate-300">
                {{ status }}
            </p>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">
                {{ content.title }}
            </h1>
            <p class="text-base leading-relaxed text-slate-600">
                {{ content.detail }}
            </p>

            <Link
                :href="homeRoute.url()"
                class="text-base font-medium text-slate-900 underline-offset-2 hover:underline"
            >
                Retour à l'accueil
            </Link>
        </div>
    </main>
</template>
