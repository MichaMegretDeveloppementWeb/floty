<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import DriverBadge from '@/Components/Domain/Driver/DriverBadge.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { show as driverShowRoute } from '@/routes/user/drivers';

const props = defineProps<{
    drivers: App.Data.User.Company.CompanyDriverRowData[];
}>();

const showInactive = ref(false);

function visibleDrivers(): App.Data.User.Company.CompanyDriverRowData[] {
    if (showInactive.value) {
        return props.drivers;
    }

    return props.drivers.filter((d) => d.isCurrentlyActive);
}

function formatDate(value: string | null): string {
    if (value === null) {
        return '-';
    }

    const [y, m, d] = value.split('-');

    return `${d}/${m}/${y}`;
}
</script>

<template>
    <Card>
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold text-slate-900">
                    Conducteurs
                </h3>
                <p class="text-sm text-slate-500">
                    {{
                        drivers.filter((d) => d.isCurrentlyActive).length
                    }}
                    actif(s) sur {{ drivers.length }} total.
                </p>
            </div>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="text-sm text-slate-600 hover:underline"
                    @click="showInactive = !showInactive"
                >
                    {{
                        showInactive
                            ? 'Masquer les sortis'
                            : 'Inclure les sortis'
                    }}
                </button>
            </div>
        </div>

        <div
            v-if="visibleDrivers().length === 0"
            class="mt-4 text-sm text-slate-500"
        >
            Aucun conducteur à afficher.
        </div>

        <table v-else class="mt-4 w-full text-sm">
            <thead
                class="border-b border-slate-200 text-left text-xs text-slate-500 uppercase"
            >
                <tr>
                    <th class="pb-2">Conducteur</th>
                    <th class="pb-2">Entrée</th>
                    <th class="pb-2">Sortie</th>
                    <th class="pb-2">Contrats</th>
                    <th class="pb-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="d in visibleDrivers()"
                    :key="d.pivotId"
                    class="border-b border-slate-100 last:border-0"
                >
                    <td class="py-3">
                        <Link
                            :href="driverShowRoute(d.driverId).url"
                            class="text-blue-700 hover:underline"
                        >
                            <DriverBadge
                                :full-name="d.fullName"
                                :initials="d.initials"
                            />
                        </Link>
                    </td>
                    <td class="py-3 text-slate-700">
                        {{ formatDate(d.joinedAt) }}
                    </td>
                    <td class="py-3">
                        <span
                            v-if="d.isCurrentlyActive"
                            class="rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700"
                        >
                            Actif
                        </span>
                        <span v-else class="text-slate-700">{{
                            formatDate(d.leftAt)
                        }}</span>
                    </td>
                    <td class="py-3 text-slate-700">{{ d.contractsCount }}</td>
                    <td class="py-3 text-right">
                        <Link :href="driverShowRoute(d.driverId).url">
                            <Button variant="ghost" size="sm">Voir</Button>
                        </Link>
                    </td>
                </tr>
            </tbody>
        </table>
    </Card>
</template>
