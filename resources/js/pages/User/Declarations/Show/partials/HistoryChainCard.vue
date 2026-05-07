<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { History } from 'lucide-vue-next';
import Card from '@/Components/Ui/Card/Card.vue';
import StatusPill from '@/Components/Ui/StatusPill/StatusPill.vue';
import { show as showRoute } from '@/routes/user/declarations';
import { badgeForDeclaration } from '@/Utils/format/declarationStatus';
import { formatDateFr } from '@/Utils/format/formatDateFr';

defineProps<{
    history: App.Data.User.FiscalDeclaration.DeclarationListItemData[];
    currentId: number;
}>();
</script>

<template>
    <Card v-if="history.length > 1">
        <template #header>
            <div class="flex items-center gap-2">
                <History :size="18" :stroke-width="1.75" class="text-slate-500" />
                <h2 class="text-base font-semibold text-slate-900">Historique</h2>
            </div>
        </template>

        <ul class="flex flex-col gap-2">
            <li
                v-for="entry in history"
                :key="entry.id"
                :class="[
                    'flex items-center gap-3 rounded-md border px-3 py-2',
                    entry.id === currentId
                        ? 'border-blue-200 bg-blue-50'
                        : 'border-slate-200 bg-white',
                ]"
            >
                <Link
                    v-if="entry.id !== currentId"
                    :href="showRoute.url({ declaration: entry.id })"
                    class="grow flex items-center gap-3 text-left"
                >
                    <div class="grow flex flex-col">
                        <span class="text-sm text-slate-700">
                            Version #{{ entry.id }}
                            <span v-if="entry.generatedAt" class="font-mono text-xs text-slate-400">
                                · {{ formatDateFr(entry.generatedAt) }}
                            </span>
                        </span>
                    </div>
                    <StatusPill :tone="badgeForDeclaration(entry.status, entry.isObsolete).tone">
                        {{ badgeForDeclaration(entry.status, entry.isObsolete).label }}
                    </StatusPill>
                </Link>
                <div v-else class="grow flex items-center gap-3">
                    <div class="grow flex flex-col">
                        <span class="text-sm font-medium text-slate-900">
                            Version actuelle (#{{ entry.id }})
                            <span v-if="entry.generatedAt" class="font-mono text-xs text-slate-500">
                                · {{ formatDateFr(entry.generatedAt) }}
                            </span>
                        </span>
                    </div>
                    <StatusPill :tone="badgeForDeclaration(entry.status, entry.isObsolete).tone">
                        {{ badgeForDeclaration(entry.status, entry.isObsolete).label }}
                    </StatusPill>
                </div>
            </li>
        </ul>
    </Card>
</template>
