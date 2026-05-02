<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, IdCard } from 'lucide-vue-next';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { useEditDriverForm } from '@/Composables/Driver/useDriverForm';
import { show as showRoute } from '@/routes/user/drivers';

const props = defineProps<{
    driver: { id: number; firstName: string; lastName: string };
}>();

const { form, submit } = useEditDriverForm(props.driver);
</script>

<template>
    <Head title="Modifier le conducteur" />

    <UserLayout>
        <div class="mx-auto flex max-w-3xl flex-col gap-6">
            <Link
                :href="showRoute(props.driver.id).url"
                class="inline-flex items-center gap-1 text-sm text-slate-500 transition-colors hover:text-slate-700"
            >
                <ChevronLeft :size="16" :stroke-width="1.75" />
                Retour à la fiche
            </Link>

            <header>
                <p class="eyebrow mb-1">Données · Conducteurs</p>
                <h1
                    class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
                >
                    Modifier le conducteur
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Seuls le prénom et le nom peuvent être modifiés ici. Les
                    rattachements aux entreprises se gèrent depuis la fiche.
                </p>
            </header>

            <form class="flex flex-col gap-8" @submit.prevent="submit">
                <section
                    class="flex flex-col gap-5 rounded-xl border border-slate-200 bg-white p-6 md:p-8"
                >
                    <header class="flex items-start gap-3">
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700"
                        >
                            <IdCard :size="18" :stroke-width="1.75" />
                        </span>
                        <div class="flex flex-col">
                            <h2 class="text-base font-semibold text-slate-900">
                                Identité
                            </h2>
                            <p class="text-sm text-slate-500">
                                La modification s'applique partout où le
                                conducteur apparaît, y compris sur les contrats
                                déjà créés.
                            </p>
                        </div>
                    </header>

                    <div
                        class="grid grid-cols-1 gap-x-5 gap-y-6 md:grid-cols-2"
                    >
                        <TextInput
                            v-model="form.first_name"
                            label="Prénom"
                            :error="form.errors.first_name"
                            required
                        />
                        <TextInput
                            v-model="form.last_name"
                            label="Nom"
                            :error="form.errors.last_name"
                            required
                        />
                    </div>
                </section>

                <div class="flex justify-end gap-3 pt-2">
                    <Link :href="showRoute(props.driver.id).url">
                        <Button type="button" variant="ghost">Annuler</Button>
                    </Link>
                    <Button type="submit" :loading="form.processing">
                        Enregistrer
                    </Button>
                </div>
            </form>
        </div>
    </UserLayout>
</template>
