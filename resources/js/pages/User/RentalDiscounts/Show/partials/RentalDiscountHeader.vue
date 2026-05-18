<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import RentalDiscountPill from '@/Components/Domain/RentalDiscount/RentalDiscountPill.vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import { show as companyShowRoute } from '@/routes/user/companies';
import { index as rentalDiscountsIndexRoute } from '@/routes/user/rental-discounts';
import { formatDateFr } from '@/Utils/format/formatDateFr';

defineProps<{
    rentalDiscount: App.Data.User.RentalDiscount.RentalDiscountData;
}>();

const statusToTone = {
    active: 'emerald',
    planned: 'amber',
    expired: 'slate',
} as const;

const statusLabelMap = {
    active: 'Active',
    planned: 'Planifiée',
    expired: 'Expirée',
} as const;
</script>

<template>
    <div class="flex flex-col gap-3">
        <Link
            :href="rentalDiscountsIndexRoute.url()"
            class="inline-flex w-fit items-center gap-1 text-xs font-medium text-slate-500 transition-colors hover:text-slate-900"
        >
            <ChevronLeft :size="14" :stroke-width="1.75" />
            Réductions commerciales
        </Link>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-baseline sm:justify-between sm:gap-6">
            <div class="flex flex-col gap-1.5">
                <div class="flex items-center gap-2">
                    <RentalDiscountPill
                        :basis-points="rentalDiscount.discountBasisPoints"
                        :tone="statusToTone[rentalDiscount.status as keyof typeof statusToTone]"
                        size="md"
                    />
                    <span
                        class="text-[11px] font-semibold uppercase tracking-[0.08em]"
                        :class="{
                            'text-emerald-700': rentalDiscount.status === 'active',
                            'text-amber-700': rentalDiscount.status === 'planned',
                            'text-slate-500': rentalDiscount.status === 'expired',
                        }"
                    >
                        {{ statusLabelMap[rentalDiscount.status as keyof typeof statusLabelMap] }}
                    </span>
                </div>
                <h1 class="text-[28px] font-semibold leading-none tracking-tight text-slate-900">
                    {{ rentalDiscount.label ?? 'Réduction sans libellé' }}
                </h1>
                <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                    <Link
                        :href="companyShowRoute.url({ company: rentalDiscount.companyId })"
                        class="inline-flex hover:opacity-80"
                    >
                        <CompanyTag
                            :name="rentalDiscount.companyLegalName"
                            :initials="rentalDiscount.companyShortCode"
                            :color="rentalDiscount.companyColor"
                        />
                    </Link>
                    <span aria-hidden="true">·</span>
                    <span>
                        Du {{ formatDateFr(rentalDiscount.startDate) }}
                        au {{ formatDateFr(rentalDiscount.endDate) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
