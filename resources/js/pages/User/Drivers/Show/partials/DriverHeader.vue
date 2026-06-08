<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, Mail } from 'lucide-vue-next';
import { computed } from 'vue';
import DriverBadge from '@/Components/Domain/Driver/DriverBadge.vue';
import { index as indexRoute } from '@/routes/user/drivers';

const props = defineProps<{
    driver: App.Data.User.Driver.DriverData;
}>();

const activeMembershipsCount = computed<number>(
    () => props.driver.memberships.filter((m) => m.isCurrentlyActive).length,
);

const subTitle = computed<string>(() => {
    const total = props.driver.memberships.length;
    const totalLabel = `${total} entreprise${total > 1 ? 's' : ''}`;
    const activeLabel = `${activeMembershipsCount.value} active${activeMembershipsCount.value > 1 ? 's' : ''}`;
    const contracts = props.driver.contractsCount;
    const contractsLabel = `${contracts} location${contracts > 1 ? 's' : ''}`;

    return `${totalLabel} · ${activeLabel} · ${contractsLabel}`;
});
</script>

<template>
    <div class="flex flex-col gap-4">
        <Link
            :href="indexRoute.url()"
            class="inline-flex items-center gap-1 text-sm text-slate-500 transition-colors hover:text-slate-700"
        >
            <ChevronLeft :size="16" :stroke-width="1.75" />
            Conducteurs
        </Link>

        <div class="flex flex-col gap-1">
            <DriverBadge
                :full-name="props.driver.fullName"
                :initials="props.driver.initials"
                size="lg"
            />
            <p class="ml-15 text-sm text-slate-500">{{ subTitle }}</p>
            <p
                class="ml-15 flex items-center gap-1.5 text-sm"
                :class="props.driver.email ? 'text-slate-600' : 'text-slate-400'"
            >
                <Mail :size="14" :stroke-width="1.75" class="shrink-0" />
                <a
                    v-if="props.driver.email"
                    :href="`mailto:${props.driver.email}`"
                    class="transition-colors hover:text-slate-900"
                >
                    {{ props.driver.email }}
                </a>
                <span v-else>Aucune adresse email</span>
            </p>
        </div>
    </div>
</template>
