<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { edit as editRoute, index as rentalDiscountsIndexRoute } from '@/routes/user/rental-discounts';

const props = defineProps<{
    rentalDiscount: App.Data.User.RentalDiscount.RentalDiscountData;
}>();

defineEmits<{
    'open-delete': [];
}>();

const statusMap = {
    active: { label: 'Active', dotClass: 'bg-emerald-500', textClass: 'text-emerald-700' },
    planned: { label: 'Planifiée', dotClass: 'bg-amber-500', textClass: 'text-amber-700' },
    expired: { label: 'Expirée', dotClass: 'bg-slate-400', textClass: 'text-slate-500' },
} as const;

const statusInfo = computed(() => statusMap[props.rentalDiscount.status as keyof typeof statusMap]);

const periodSubtitle = computed<string>(() => {
    const [sy, sm, sd] = props.rentalDiscount.startDate.split('-');
    const [ey, em, ed] = props.rentalDiscount.endDate.split('-');
    const monthNames = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    const sameYear = sy === ey;
    const sameMonth = sameYear && sm === em;
    const sMonth = monthNames[parseInt(sm!, 10) - 1];
    const eMonth = monthNames[parseInt(em!, 10) - 1];
    const startLabel = sameMonth
        ? `${parseInt(sd!, 10)}`
        : sameYear
            ? `${parseInt(sd!, 10)} ${sMonth}`
            : `${parseInt(sd!, 10)} ${sMonth} ${sy}`;
    const endLabel = `${parseInt(ed!, 10)} ${eMonth} ${ey}`;

    return `Du ${startLabel} au ${endLabel}`;
});
</script>

<template>
    <div class="flex flex-col gap-4">
        <Link
            :href="rentalDiscountsIndexRoute.url()"
            class="inline-flex w-fit items-center gap-1 text-xs font-medium text-slate-500 transition-colors hover:text-slate-900"
        >
            <ChevronLeft :size="14" :stroke-width="1.75" />
            Réductions commerciales
        </Link>

        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between lg:gap-6">
            <div class="flex flex-col gap-1.5">
                <p class="eyebrow">
                    Réduction commerciale · {{ rentalDiscount.companyShortCode }} · {{ rentalDiscount.companyLegalName }}
                </p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
                    {{ rentalDiscount.label ?? 'Réduction sans libellé' }}
                </h1>
                <p class="text-sm text-slate-500">
                    {{ periodSubtitle }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5">
                    <span :class="['size-1.5 rounded-full', statusInfo.dotClass]" aria-hidden="true" />
                    <span :class="['text-xs font-medium', statusInfo.textClass]">
                        {{ statusInfo.label }}
                    </span>
                </span>
                <div class="flex items-center gap-1.5">
                    <Link
                        :href="editRoute.url({ rentalDiscount: rentalDiscount.id })"
                        class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50"
                    >
                        <Pencil :size="12" :stroke-width="1.75" />
                        Modifier
                    </Link>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700"
                        @click="$emit('open-delete')"
                    >
                        <Trash2 :size="12" :stroke-width="1.75" />
                        Supprimer
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
