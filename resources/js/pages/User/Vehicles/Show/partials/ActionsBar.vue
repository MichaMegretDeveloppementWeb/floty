<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Pencil } from 'lucide-vue-next';
import Button from '@/Components/Ui/Button/Button.vue';
import { edit as vehiclesEditRoute } from '@/routes/user/vehicles';

const props = defineProps<{
    vehicle: App.Data.User.Vehicle.VehicleData;
}>();
</script>

<template>
    <div
        class="flex flex-wrap items-center justify-end gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3"
    >
        <Link :href="vehiclesEditRoute.url({ vehicle: props.vehicle.id })">
            <Button variant="secondary" size="sm">
                <template #icon-left>
                    <Pencil :size="14" :stroke-width="1.75" />
                </template>
                Modifier
            </Button>
        </Link>
    </div>
</template>
