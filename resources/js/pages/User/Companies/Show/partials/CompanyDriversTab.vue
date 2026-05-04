<script setup lang="ts">
/**
 * Onglet « Conducteurs » de la page Show Company (chantier M.3).
 *
 * Symétrique de `DriverCompaniesSection` côté Driver Show : permet
 * d'ajouter, sortir et détacher un driver depuis la fiche Company.
 *
 * Les modals (Add, Leave) vivent localement à cet onglet — ils
 * persistent tant que l'utilisateur reste sur l'onglet et sont
 * remontés au démontage. Cela évite de polluer `Companies/Show/Index`
 * avec un state propre à un seul onglet.
 *
 * Les actions (POST/PATCH/DELETE) réutilisent les routes côté Driver
 * (`/drivers/{driver}/memberships`) — le pivot est unique, pas besoin
 * de doubler les endpoints.
 */
import { router } from '@inertiajs/vue3';
import { Plus, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AddCompanyDriverModal from '@/Components/Domain/Driver/AddCompanyDriverModal.vue';
import DriverBadge from '@/Components/Domain/Driver/DriverBadge.vue';
import LeaveDriverCompanyModal from '@/Components/Domain/Driver/LeaveDriverCompanyModal.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import { show as driverShowRoute } from '@/routes/user/drivers';
import { destroy as detachRoute } from '@/routes/user/drivers/memberships';

type DriverOption = { id: number; fullName: string; initials: string };

const props = defineProps<{
    companyId: number;
    companyLegalName: string;
    drivers: App.Data.User.Company.CompanyDriverRowData[];
    availableDrivers: DriverOption[];
}>();

const showInactive = ref(false);
const showAddModal = ref(false);
const leaveDriverId = ref<number | null>(null);
const leaveDriverFullName = ref<string>('');
const detaching = ref<number | null>(null);

const activeCount = computed<number>(
    () => props.drivers.filter((d) => d.isCurrentlyActive).length,
);

const visibleDrivers = computed<App.Data.User.Company.CompanyDriverRowData[]>(
    () =>
        showInactive.value
            ? props.drivers
            : props.drivers.filter((d) => d.isCurrentlyActive),
);

const existingDriverIds = computed<number[]>(() =>
    props.drivers.filter((d) => d.isCurrentlyActive).map((d) => d.driverId),
);

function openLeave(row: App.Data.User.Company.CompanyDriverRowData): void {
    leaveDriverId.value = row.driverId;
    leaveDriverFullName.value = row.fullName;
}

function closeLeave(): void {
    leaveDriverId.value = null;
    leaveDriverFullName.value = '';
}

function detach(row: App.Data.User.Company.CompanyDriverRowData): void {
    if (row.contractsCount > 0) {
        return;
    }

    if (
        !confirm(
            `Détacher le rattachement avec ${row.fullName} ? Les dates d'entrée et de sortie seront perdues.`,
        )
    ) {
        return;
    }

    detaching.value = row.pivotId;
    router.delete(detachRoute([row.driverId, row.pivotId]).url, {
        preserveScroll: true,
        onFinish: () => {
            detaching.value = null;
        },
    });
}

function formatDate(value: string | null): string {
    if (value === null) {
        return '-';
    }

    const [y, m, d] = value.split('-');

    return `${d}/${m}/${y}`;
}

function onRowClick(driverId: number): void {
    router.visit(driverShowRoute(driverId).url);
}
</script>

<template>
    <Card>
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold text-slate-900">
                    Conducteurs
                </h3>
                <p class="text-sm text-slate-500">
                    {{ activeCount }} actif{{ activeCount > 1 ? 's' : '' }} sur
                    {{ drivers.length }} au total
                </p>
            </div>
            <div class="flex items-center gap-2">
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
                <Button
                    variant="secondary"
                    size="sm"
                    @click="showAddModal = true"
                >
                    <template #icon-left>
                        <Plus :size="14" :stroke-width="1.75" />
                    </template>
                    Ajouter
                </Button>
            </div>
        </div>

        <div
            v-if="drivers.length === 0"
            class="mt-4 flex flex-col items-center gap-2 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center"
        >
            <span
                class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white text-slate-400"
            >
                <Users :size="20" :stroke-width="1.75" />
            </span>
            <p class="text-sm font-medium text-slate-700">
                Aucun conducteur rattaché
            </p>
            <p class="text-xs text-slate-500">
                Ajoutez un premier conducteur pour pouvoir lui affecter des
                contrats.
            </p>
        </div>

        <div
            v-else-if="visibleDrivers.length === 0"
            class="mt-4 text-sm text-slate-500"
        >
            Aucun conducteur actif. Activez « Inclure les sortis » pour voir
            l'historique.
        </div>

        <table v-else class="mt-4 w-full text-sm">
            <thead
                class="border-b border-slate-200 text-left text-xs text-slate-500 uppercase"
            >
                <tr>
                    <th class="pb-3 font-medium">Conducteur</th>
                    <th class="pb-3 font-medium">Entrée</th>
                    <th class="pb-3 font-medium">Sortie</th>
                    <th class="pb-3 font-medium">Contrats</th>
                    <th class="pb-3 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="d in visibleDrivers"
                    :key="d.pivotId"
                    class="cursor-pointer border-b border-slate-100 transition-colors duration-[120ms] ease-out last:border-0 hover:bg-slate-50"
                    @click="onRowClick(d.driverId)"
                >
                    <td class="py-4">
                        <DriverBadge
                            :full-name="d.fullName"
                            :initials="d.initials"
                        />
                    </td>
                    <td class="py-4 text-slate-700">
                        {{ formatDate(d.joinedAt) }}
                    </td>
                    <td class="py-4">
                        <span
                            v-if="d.isCurrentlyActive"
                            class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-emerald-500"
                            />
                            Actif
                        </span>
                        <span v-else class="text-slate-700">{{
                            formatDate(d.leftAt)
                        }}</span>
                    </td>
                    <td class="py-4 font-medium text-slate-700 tabular-nums">
                        {{ d.contractsCount }}
                    </td>
                    <td class="py-4 text-right">
                        <Button
                            v-if="d.isCurrentlyActive"
                            variant="secondary"
                            size="sm"
                            @click.stop="openLeave(d)"
                        >
                            Sortir
                        </Button>
                        <Button
                            v-else-if="d.contractsCount === 0"
                            variant="ghost"
                            size="sm"
                            :loading="detaching === d.pivotId"
                            @click.stop="detach(d)"
                        >
                            Détacher
                        </Button>
                    </td>
                </tr>
            </tbody>
        </table>

        <LeaveDriverCompanyModal
            v-if="leaveDriverId !== null"
            :driver-id="leaveDriverId"
            :company-id="props.companyId"
            :driver-full-name="leaveDriverFullName"
            :company-name="props.companyLegalName"
            @close="closeLeave"
        />

        <AddCompanyDriverModal
            v-if="showAddModal"
            :company-id="props.companyId"
            :existing-driver-ids="existingDriverIds"
            :available-drivers="props.availableDrivers"
            @close="showAddModal = false"
        />
    </Card>
</template>
