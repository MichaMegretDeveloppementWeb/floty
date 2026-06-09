<script setup lang="ts">
/** Dashboard panel listing regulatory controls reaching échéance across the fleet. */
import { Link } from '@inertiajs/vue3';
import { ChevronRight, ShieldCheck } from 'lucide-vue-next';
import { index as vehiclesIndexRoute } from '@/routes/user/vehicles';
import ControlDueRow from './ControlDueRow.vue';

type ControlsDue = App.Data.User.Dashboard.DashboardControlsDueData;

defineProps<{
    /** Total controls needing attention; powers the header counter. */
    count: ControlsDue['count'];
    items: ControlsDue['items'];
}>();
</script>

<template>
    <article class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-5">
        <header class="flex items-center gap-3">
            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700"
            >
                <ShieldCheck :size="18" :stroke-width="1.75" aria-hidden="true" />
            </div>
            <div class="flex flex-col gap-0.5">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Contrôles réglementaires à échéance
                </p>
                <p class="text-sm text-slate-500">
                    <template v-if="count > 0">
                        <span class="font-mono font-medium tabular-nums text-slate-900">
                            {{ count }}
                        </span>
                        à traiter sur le parc
                    </template>
                    <template v-else>
                        À jour sur l'ensemble du parc
                    </template>
                </p>
            </div>
            <Link
                v-if="count > 0"
                :href="vehiclesIndexRoute.url({ query: { controlsDue: 1 } })"
                class="ml-auto inline-flex shrink-0 items-center gap-0.5 text-sm font-medium text-blue-600 transition-colors duration-[120ms] ease-out hover:text-blue-700"
            >
                Voir tout
                <ChevronRight :size="15" :stroke-width="2" aria-hidden="true" />
            </Link>
        </header>

        <ul v-if="items.length > 0" class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <li
                v-for="item in items"
                :key="`${item.vehicleId}-${item.controlName}-${item.nextDueDate}`"
            >
                <ControlDueRow :item="item" />
            </li>
        </ul>

        <p v-else class="rounded-lg bg-slate-50 px-3 py-3 text-sm text-slate-600">
            Aucun contrôle réglementaire à échéance. Le parc est à jour.
        </p>
    </article>
</template>
