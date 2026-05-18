<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Pencil, Trash2 } from 'lucide-vue-next';
import Card from '@/Components/Ui/Card/Card.vue';
import { edit as editRoute } from '@/routes/user/rental-discounts';

defineProps<{
    rentalDiscountId: number;
}>();

const emit = defineEmits<{
    'open-delete': [];
}>();
</script>

<template>
    <Card>
        <template #header>
            <h2 class="text-base font-semibold text-slate-900">
                Actions
            </h2>
        </template>
        <div class="flex flex-col gap-2">
            <Link
                :href="editRoute.url({ rentalDiscount: rentalDiscountId })"
                class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
            >
                <Pencil :size="14" :stroke-width="1.75" />
                Modifier
            </Link>
            <button
                type="button"
                class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-rose-200 bg-white px-3 py-2 text-sm font-medium text-rose-700 transition-colors hover:bg-rose-50"
                @click="emit('open-delete')"
            >
                <Trash2 :size="14" :stroke-width="1.75" />
                Supprimer
            </button>
        </div>
    </Card>
</template>
