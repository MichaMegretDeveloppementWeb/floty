<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Building2, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ActionsMenu from '@/Components/Ui/ActionsMenu/ActionsMenu.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import { show as companyShowRoute } from '@/routes/user/companies';
import { destroy as detachRoute, update as updateRoute } from '@/routes/user/drivers/memberships';

type Membership = App.Data.User.Driver.DriverCompanyMembershipData;

const props = defineProps<{
    driverId: number;
    memberships: Membership[];
}>();

const emit = defineEmits<{
    'open-leave': [companyId: number];
    'open-add': [];
    'open-edit': [membership: Membership];
}>();

const detaching = ref<number | null>(null);

const activeCount = computed<number>(
    () => props.memberships.filter((m) => m.isCurrentlyActive).length,
);

function detach(membership: Membership): void {
    if (membership.contractsCount > 0) {
        return;
    }

    if (
        !confirm(
            `Détacher le rattachement avec ${membership.companyShortCode} ? Les dates d'entrée et de sortie seront perdues.`,
        )
    ) {
        return;
    }

    detaching.value = membership.pivotId;
    router.delete(detachRoute.url([props.driverId, membership.pivotId]), {
        preserveScroll: true,
        onFinish: () => {
            detaching.value = null;
        },
    });
}

function reactivate(membership: Membership): void {
    if (
        !confirm(
            `Réactiver le rattachement avec ${membership.companyShortCode} ? La date de sortie sera effacée.`,
        )
    ) {
        return;
    }

    // Réutilise l'endpoint update : `left_at: null` réactive la membership
    // (cf. UpdateDriverCompanyMembershipAction). On préserve `joined_at`.
    router.patch(
        updateRoute.url([props.driverId, membership.pivotId]),
        { joined_at: membership.joinedAt, left_at: null },
        { preserveScroll: true },
    );
}

function formatDate(value: string | null): string {
    if (value === null) {
        return '-';
    }

    const [y, m, d] = value.split('-');

    return `${d}/${m}/${y}`;
}

function onRowClick(companyId: number): void {
    router.visit(companyShowRoute.url(companyId));
}
</script>

<template>
    <section
        class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6"
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-slate-900">
                    Entreprises
                </h2>
                <p class="text-sm text-slate-500">
                    {{ activeCount }} active{{ activeCount > 1 ? 's' : '' }} sur
                    {{ memberships.length }} au total
                </p>
            </div>
            <Button variant="secondary" size="sm" @click="emit('open-add')">
                <template #icon-left>
                    <Plus :size="14" :stroke-width="1.75" />
                </template>
                Ajouter
            </Button>
        </div>

        <div
            v-if="memberships.length === 0"
            class="flex flex-col items-center gap-2 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center"
        >
            <span
                class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white text-slate-400"
            >
                <Building2 :size="20" :stroke-width="1.75" />
            </span>
            <p class="text-sm font-medium text-slate-700">
                Aucune entreprise rattachée
            </p>
            <p class="text-xs text-slate-500">
                Ajoutez une première entreprise pour pouvoir affecter des
                locations à ce conducteur.
            </p>
        </div>

        <table v-else class="w-full text-sm">
            <thead
                class="border-b border-slate-200 text-left text-xs text-slate-500 uppercase"
            >
                <tr>
                    <th class="pb-3 font-medium">Entreprise</th>
                    <th class="pb-3 font-medium">Entrée</th>
                    <th class="pb-3 font-medium">Sortie</th>
                    <th class="pb-3 font-medium">Locations</th>
                    <th class="pb-3 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="m in memberships"
                    :key="m.pivotId"
                    tabindex="0"
                    class="cursor-pointer border-b border-slate-100 transition-colors duration-[120ms] ease-out last:border-0 hover:bg-slate-50 focus:outline-none focus-visible:bg-slate-50 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-slate-200"
                    @click="onRowClick(m.companyId)"
                    @keydown.enter="onRowClick(m.companyId)"
                    @keydown.space.prevent="onRowClick(m.companyId)"
                >
                    <td class="py-4">
                        <CompanyTag
                            :name="m.companyLegalName"
                            :initials="m.companyShortCode"
                            :color="m.companyColor"
                        />
                    </td>
                    <td class="py-4 text-slate-700">
                        {{ formatDate(m.joinedAt) }}
                    </td>
                    <td class="py-4">
                        <span
                            v-if="m.isCurrentlyActive"
                            class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-emerald-500"
                            />
                            Actif
                        </span>
                        <span v-else class="text-slate-700">{{
                            formatDate(m.leftAt)
                        }}</span>
                    </td>
                    <td class="py-4 font-medium text-slate-700 tabular-nums">
                        {{ m.contractsCount }}
                    </td>
                    <td class="py-4 text-right" @click.stop>
                        <ActionsMenu align="right">
                            <button type="button" @click="emit('open-edit', m)">
                                Éditer
                            </button>
                            <button
                                v-if="m.isCurrentlyActive"
                                type="button"
                                @click="emit('open-leave', m.companyId)"
                            >
                                Retirer
                            </button>
                            <template v-else>
                                <button type="button" @click="reactivate(m)">
                                    Réactiver
                                </button>
                                <button
                                    type="button"
                                    class="danger"
                                    :disabled="m.contractsCount > 0 || detaching === m.pivotId"
                                    @click="detach(m)"
                                >
                                    Détacher
                                </button>
                            </template>
                        </ActionsMenu>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
