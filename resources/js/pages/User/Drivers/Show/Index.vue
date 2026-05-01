<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AddDriverCompanyModal from '@/Components/Domain/Driver/AddDriverCompanyModal.vue';
import DriverBadge from '@/Components/Domain/Driver/DriverBadge.vue';
import LeaveDriverCompanyModal from '@/Components/Domain/Driver/LeaveDriverCompanyModal.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import {
    destroy as destroyDriverRoute,
    edit as editRoute,
    index as indexRoute,
} from '@/routes/user/drivers';
import DriverCompaniesSection from './partials/DriverCompaniesSection.vue';

const props = defineProps<{
    driver: App.Data.User.Driver.DriverData;
}>();

const leaveCompanyId = ref<number | null>(null);
const showAddModal = ref(false);

function destroy(): void {
    if (
        !confirm(
            `Supprimer définitivement le conducteur ${props.driver.fullName} ?`,
        )
    ) {
        return;
    }

    router.delete(destroyDriverRoute(props.driver.id).url, {
        preserveScroll: false,
    });
}
</script>

<template>
    <Head :title="props.driver.fullName" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link
                        :href="indexRoute().url"
                        class="text-sm text-slate-500 hover:underline"
                    >
                        ← Conducteurs
                    </Link>
                </div>
                <div class="flex gap-2">
                    <Link :href="editRoute(props.driver.id).url">
                        <Button variant="secondary">Modifier</Button>
                    </Link>
                    <Button
                        v-if="props.driver.contractsCount === 0"
                        variant="destructive"
                        @click="destroy"
                    >
                        Supprimer
                    </Button>
                </div>
            </div>

            <Card>
                <div class="flex items-center gap-4">
                    <DriverBadge
                        :full-name="props.driver.fullName"
                        :initials="props.driver.initials"
                    />
                    <div class="ml-auto text-right text-sm text-slate-600">
                        <div>{{ props.driver.contractsCount }} contrat(s)</div>
                        <div>
                            {{ props.driver.memberships.length }} membership(s)
                        </div>
                    </div>
                </div>
            </Card>

            <DriverCompaniesSection
                :driver-id="props.driver.id"
                :memberships="props.driver.memberships"
                @open-leave="(companyId) => (leaveCompanyId = companyId)"
                @open-add="showAddModal = true"
            />

            <LeaveDriverCompanyModal
                v-if="leaveCompanyId !== null"
                :driver-id="props.driver.id"
                :company-id="leaveCompanyId"
                :driver-full-name="props.driver.fullName"
                :company-name="
                    props.driver.memberships.find(
                        (m) => m.companyId === leaveCompanyId,
                    )?.companyLegalName ?? ''
                "
                @close="leaveCompanyId = null"
            />

            <AddDriverCompanyModal
                v-if="showAddModal"
                :driver-id="props.driver.id"
                :existing-company-ids="
                    props.driver.memberships.map((m) => m.companyId)
                "
                @close="showAddModal = false"
            />
        </div>
    </UserLayout>
</template>
