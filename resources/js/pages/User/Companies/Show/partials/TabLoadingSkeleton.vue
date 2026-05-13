<script setup lang="ts">
/**
 * Skeleton affiché pendant le partial reload Inertia qui charge les
 * props d'un onglet pour la première fois de la session (D5.10.V) ·
 * remplace le tab actif tant que ses props ne sont pas arrivées.
 *
 * 3 cards skeleton + barre d'en-tête · suffisamment proche du layout
 * réel des onglets (Fiscal/Billing/Contracts) pour éviter le saut
 * visuel quand les vraies données arrivent.
 */
</script>

<template>
    <div class="flex flex-col gap-6" aria-busy="true" aria-live="polite">
        <div class="rounded-md border border-slate-200 bg-white p-6">
            <div class="flex flex-col gap-3">
                <div class="h-5 w-32 animate-pulse rounded bg-slate-100" />
                <div class="h-4 w-64 animate-pulse rounded bg-slate-100" />
                <div class="mt-2 flex gap-1.5">
                    <div class="h-7 w-14 animate-pulse rounded-full bg-slate-100" />
                    <div class="h-7 w-14 animate-pulse rounded-full bg-slate-100" />
                    <div class="h-7 w-14 animate-pulse rounded-full bg-slate-100" />
                </div>
            </div>
        </div>
        <div class="rounded-md border border-slate-200 bg-white p-6">
            <div class="flex flex-col gap-3">
                <div class="h-4 w-40 animate-pulse rounded bg-slate-100" />
                <div class="h-32 w-full animate-pulse rounded bg-slate-100" />
            </div>
        </div>
        <div class="rounded-md border border-slate-200 bg-white p-6">
            <div class="flex flex-col gap-3">
                <div class="h-4 w-48 animate-pulse rounded bg-slate-100" />
                <div class="h-24 w-full animate-pulse rounded bg-slate-100" />
            </div>
        </div>
    </div>
</template>
