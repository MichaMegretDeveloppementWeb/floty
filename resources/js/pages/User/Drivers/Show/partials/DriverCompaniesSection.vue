<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Building2, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import { destroy as detachRoute } from '@/routes/user/drivers/memberships';

type Membership = App.Data.User.Driver.DriverCompanyMembershipData;

const props = defineProps<{
    driverId: number;
    memberships: Membership[];
}>();

const emit = defineEmits<{
    'open-leave': [companyId: number];
    'open-add': [];
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
            `Détacher l'appartenance avec ${membership.companyShortCode} ? Les dates d'entrée et de sortie seront perdues.`,
        )
    ) {
        return;
    }

    detaching.value = membership.pivotId;
    router.delete(detachRoute([props.driverId, membership.pivotId]).url, {
        preserveScroll: true,
        onFinish: () => {
            detaching.value = null;
        },
    });
}

function formatDate(value: string | null): string {
    if (value === null) {
        return '—';
    }

    const [y, m, d] = value.split('-');

    return `${d}/${m}/${y}`;
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
                contrats à ce conducteur.
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
                    <th class="pb-3 font-medium">Contrats</th>
                    <th class="pb-3 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="m in memberships"
                    :key="m.pivotId"
                    class="border-b border-slate-100 last:border-0"
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
                    <td class="py-4 text-right">
                        <Button
                            v-if="m.isCurrentlyActive"
                            variant="secondary"
                            size="sm"
                            @click="emit('open-leave', m.companyId)"
                        >
                            Sortir
                        </Button>
                        <Button
                            v-else-if="m.contractsCount === 0"
                            variant="ghost"
                            size="sm"
                            :loading="detaching === m.pivotId"
                            @click="detach(m)"
                        >
                            Détacher
                        </Button>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
