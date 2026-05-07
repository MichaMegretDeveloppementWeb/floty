<script setup lang="ts">
/**
 * Empty state de la Vue par entreprise (chantier P2). Affichée quand
 * aucune entreprise n'a encore été enregistrée. Invite à en créer une.
 */
import { Head, Link } from '@inertiajs/vue3';
import { Building2, Plus } from 'lucide-vue-next';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import { create as companiesCreateRoute, index as companiesIndexRoute } from '@/routes/user/companies';
</script>

<template>
    <Head title="Vue par entreprise" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <header>
                <p class="eyebrow mb-1">Planning</p>
                <h1
                    class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
                >
                    Vue par entreprise
                </h1>
            </header>

            <div
                class="flex flex-col items-center gap-4 rounded-2xl border border-slate-200 bg-white p-12 text-center"
            >
                <span
                    class="flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-500"
                    aria-hidden="true"
                >
                    <Building2 :size="24" :stroke-width="1.75" />
                </span>
                <div>
                    <p class="text-base font-medium text-slate-900">
                        Aucune entreprise enregistrée
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        Créez une entreprise pour accéder à sa Vue planning.
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-2 pt-2">
                    <Link :href="companiesCreateRoute.url()">
                        <Button>
                            <template #icon-left>
                                <Plus :size="14" :stroke-width="1.75" />
                            </template>
                            Nouvelle entreprise
                        </Button>
                    </Link>
                    <Link :href="companiesIndexRoute.url()">
                        <Button variant="secondary">
                            Voir la liste
                        </Button>
                    </Link>
                </div>
            </div>
        </div>
    </UserLayout>
</template>
