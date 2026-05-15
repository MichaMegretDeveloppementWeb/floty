<script setup lang="ts">
/**
 * Onglet « Conducteurs » de la page Show Company (chantier M.3).
 *
 * Symétrique de `DriverCompaniesSection` côté Driver Show · permet
 * d'ajouter, sortir et détacher un driver depuis la fiche Company.
 *
 * Pure présentation depuis Lot 7 D01 · toute la logique est extraite
 * dans `useCompanyDriversTab` (R9 + mémoire `feedback_vue_composables_extraction`).
 * F-40-018 fix collateral · les 2 `confirm()` natifs (detach +
 * reactivate) sont remplacés par un `ConfirmModal` Vue cohérent avec
 * le design system Floty (focus trap, esc/click outside, accessibilité).
 */
import { Plus, Users } from 'lucide-vue-next';
import AddCompanyDriverModal from '@/Components/Domain/Driver/AddCompanyDriverModal.vue';
import DriverBadge from '@/Components/Domain/Driver/DriverBadge.vue';
import EditDriverCompanyMembershipModal from '@/Components/Domain/Driver/EditDriverCompanyMembershipModal.vue';
import LeaveDriverCompanyModal from '@/Components/Domain/Driver/LeaveDriverCompanyModal.vue';
import ActionsMenu from '@/Components/Ui/ActionsMenu/ActionsMenu.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import ConfirmModal from '@/Components/Ui/ConfirmModal/ConfirmModal.vue';
import { useCompanyDriversTab } from '@/Composables/Company/Show/useCompanyDriversTab';

type DriverOption = { id: number; fullName: string; initials: string };

const props = defineProps<{
    companyId: number;
    companyLegalName: string;
    drivers: App.Data.User.Company.CompanyDriverRowData[];
    availableDrivers: DriverOption[];
}>();

const {
    showInactive,
    showAddModal,
    leaveDriverId,
    leaveDriverFullName,
    editRow,
    detaching,
    confirmAction,
    activeCount,
    visibleDrivers,
    existingDriverIds,
    toggleShowInactive,
    openAdd,
    openLeave,
    closeLeave,
    openEdit,
    closeEdit,
    requestDetach,
    requestReactivate,
    closeConfirm,
    runConfirmAction,
    formatDate,
    onRowClick,
} = useCompanyDriversTab(props);
</script>

<template>
    <div class="flex flex-col gap-6">
        <!--
            Header éditorial · h2 + meta + actions. Plus de Card
            wrapping · alignement sur le pattern Linear-éditorial
            (refonte D5.10.W étape 6).
        -->
        <header class="flex flex-col gap-3 sm:flex-row sm:items-baseline sm:justify-between sm:gap-6">
            <div class="flex flex-col gap-1">
                <h2 class="text-[28px] font-semibold leading-none tracking-tight text-slate-900">
                    Conducteurs
                </h2>
                <p class="text-sm text-slate-500">
                    {{ activeCount }} actif{{ activeCount > 1 ? 's' : '' }} sur
                    {{ drivers.length }} au total
                </p>
            </div>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="cursor-pointer text-sm text-slate-600 transition-colors duration-[120ms] hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-200"
                    @click="toggleShowInactive"
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
                    @click="openAdd"
                >
                    <template #icon-left>
                        <Plus :size="14" :stroke-width="1.75" />
                    </template>
                    Ajouter
                </Button>
            </div>
        </header>

        <div
            v-if="drivers.length === 0"
            class="flex flex-col items-center gap-2 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center"
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
                locations.
            </p>
        </div>

        <div
            v-else-if="visibleDrivers.length === 0"
            class="text-sm text-slate-500"
        >
            Aucun conducteur actif. Activez « Inclure les sortis » pour voir
            l'historique.
        </div>

        <table v-else class="w-full text-sm">
            <thead
                class="border-b border-slate-200 text-left text-xs text-slate-500 uppercase"
            >
                <tr>
                    <th class="pb-3 font-medium">Conducteur</th>
                    <th class="pb-3 font-medium">Entrée</th>
                    <th class="pb-3 font-medium">Sortie</th>
                    <th class="pb-3 font-medium">Locations</th>
                    <th class="pb-3 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="d in visibleDrivers"
                    :key="d.pivotId"
                    tabindex="0"
                    class="cursor-pointer border-b border-slate-100 transition-colors duration-[120ms] ease-out last:border-0 hover:bg-slate-50 focus:outline-none focus-visible:bg-slate-50 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-slate-200"
                    @click="onRowClick(d.driverId)"
                    @keydown.enter="onRowClick(d.driverId)"
                    @keydown.space.prevent="onRowClick(d.driverId)"
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
                    <td class="py-4 text-right" @click.stop>
                        <ActionsMenu align="right">
                            <button type="button" @click="openEdit(d)">
                                Éditer
                            </button>
                            <button
                                v-if="d.isCurrentlyActive"
                                type="button"
                                @click="openLeave(d)"
                            >
                                Retirer
                            </button>
                            <template v-else>
                                <button type="button" @click="requestReactivate(d)">
                                    Réactiver
                                </button>
                                <button
                                    type="button"
                                    class="danger"
                                    :disabled="d.contractsCount > 0 || detaching === d.pivotId"
                                    @click="requestDetach(d)"
                                >
                                    Détacher
                                </button>
                            </template>
                        </ActionsMenu>
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


        <EditDriverCompanyMembershipModal
            v-if="editRow !== null"
            :driver-id="editRow.driverId"
            :pivot-id="editRow.pivotId"
            :company-short-code="props.companyLegalName"
            :current-joined-at="editRow.joinedAt"
            :current-left-at="editRow.leftAt"
            @close="closeEdit"
        />

        <!-- F-40-018 (Lot 7 D01) · ConfirmModal Vue remplace les 2
             window.confirm() natifs (detach + reactivate). État unifié
             via `confirmAction` du composable. -->
        <ConfirmModal
            v-if="confirmAction !== null"
            :open="true"
            :tone="confirmAction.kind === 'detach' ? 'danger' : 'default'"
            :title="confirmAction.title"
            :message="confirmAction.message"
            :confirm-label="confirmAction.confirmLabel"
            @confirm="runConfirmAction"
            @update:open="(v: boolean) => { if (!v) closeConfirm(); }"
        />
    </div>
</template>
