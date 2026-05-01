<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InputError from '@/Components/Ui/InputError/InputError.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { useEditDriverForm } from '@/Composables/Driver/useDriverForm';
import { show as showRoute } from '@/routes/user/drivers';

const props = defineProps<{
    driver: { id: number; firstName: string; lastName: string };
}>();

const { form, submit } = useEditDriverForm(props.driver);
</script>

<template>
    <Head title="Modifier conducteur" />

    <UserLayout>
        <div class="mx-auto flex max-w-2xl flex-col gap-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900">
                    Modifier conducteur
                </h1>
                <Link
                    :href="showRoute(props.driver.id).url"
                    class="text-sm text-slate-600 hover:underline"
                >
                    Annuler
                </Link>
            </div>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div>
                    <FieldLabel for="first_name">Prénom</FieldLabel>
                    <TextInput id="first_name" v-model="form.first_name" />
                    <InputError :message="form.errors.first_name" />
                </div>

                <div>
                    <FieldLabel for="last_name">Nom</FieldLabel>
                    <TextInput id="last_name" v-model="form.last_name" />
                    <InputError :message="form.errors.last_name" />
                </div>

                <div class="flex justify-end">
                    <Button type="submit" :loading="form.processing"
                        >Enregistrer</Button
                    >
                </div>
            </form>
        </div>
    </UserLayout>
</template>
