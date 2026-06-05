<script setup lang="ts">
/**
 * Bandeau d'alerte « Planificateur inactif » (Chantier B / B3). Visible quand le
 * heartbeat du cron est périmé (prop Inertia partagée `schedulerHeartbeatStale`).
 * Pleine largeur du conteneur qui l'accueille : placé dans le domaine Contrôles
 * (catalogue + onglet véhicule), où l'alerte a du sens.
 */
import { usePage } from '@inertiajs/vue3';
import { TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const stale = computed<boolean>(() => page.props.schedulerHeartbeatStale === true);
</script>

<template>
    <div
        v-if="stale"
        class="flex w-full items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3"
        role="alert"
    >
        <TriangleAlert
            :size="18"
            :stroke-width="1.75"
            class="mt-0.5 shrink-0 text-amber-600"
            aria-hidden="true"
        />
        <div class="flex flex-col gap-0.5">
            <p class="text-sm font-semibold text-amber-800">Planificateur inactif</p>
            <p class="text-sm text-amber-700">
                Les rappels automatiques de contrôles ne sont peut-être plus envoyés.
                Contactez l'administrateur.
            </p>
        </div>
    </div>
</template>
