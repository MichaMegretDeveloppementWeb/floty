<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
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

function detach(membership: Membership): void {
    if (membership.contractsCount > 0) {
        return;
    }

    if (
        !confirm(
            `Détacher définitivement la membership avec ${membership.companyShortCode} ?`,
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
    <Card>
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">Entreprises</h2>
            <Button variant="secondary" size="sm" @click="emit('open-add')">
                Ajouter une entreprise
            </Button>
        </div>

        <div
            v-if="memberships.length === 0"
            class="mt-4 text-sm text-slate-500"
        >
            Aucune entreprise rattachée.
        </div>

        <table v-else class="mt-4 w-full text-sm">
            <thead
                class="border-b border-slate-200 text-left text-xs text-slate-500 uppercase"
            >
                <tr>
                    <th class="pb-2">Entreprise</th>
                    <th class="pb-2">Entrée</th>
                    <th class="pb-2">Sortie</th>
                    <th class="pb-2">Contrats</th>
                    <th class="pb-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="m in memberships"
                    :key="m.pivotId"
                    class="border-b border-slate-100 last:border-0"
                >
                    <td class="py-3">
                        <CompanyTag
                            :name="m.companyLegalName"
                            :initials="m.companyShortCode"
                            :color="m.companyColor"
                        />
                    </td>
                    <td class="py-3 text-slate-700">
                        {{ formatDate(m.joinedAt) }}
                    </td>
                    <td class="py-3">
                        <span
                            v-if="m.isCurrentlyActive"
                            class="rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700"
                        >
                            Actif
                        </span>
                        <span v-else class="text-slate-700">{{
                            formatDate(m.leftAt)
                        }}</span>
                    </td>
                    <td class="py-3 text-slate-700">{{ m.contractsCount }}</td>
                    <td class="py-3 text-right">
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
    </Card>
</template>
