<script setup lang="ts">
import {
    AlertTriangle,
    CheckCircle2,
    Info,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';

type ToastTone = 'success' | 'error' | 'warning' | 'info';

const props = withDefaults(
    defineProps<{
        tone?: ToastTone;
        title: string;
        description?: string;
        dismissible?: boolean;
        /**
         * Durée d'affichage en ms (= TTL côté `useToasts`). Pilote la
         * barre de progression bottom qui décroît de 100 % → 0 % pendant
         * la durée d'affichage. 0 = persistant (pas de barre).
         */
        duration?: number;
    }>(),
    {
        tone: 'info',
        dismissible: true,
        duration: 0,
    },
);

const emit = defineEmits<{
    dismiss: [];
}>();

const chipClasses = computed<string>(() => {
    switch (props.tone) {
        case 'success':
            return 'bg-emerald-50 text-emerald-700';
        case 'error':
            return 'bg-rose-50 text-rose-700';
        case 'warning':
            return 'bg-amber-50 text-amber-700';
        case 'info':
            return 'bg-blue-50 text-blue-700';
        default: {
            const _exhaustive: never = props.tone;

            throw new Error(`Tonalité non gérée : ${_exhaustive as string}`);
        }
    }
});

const progressClasses = computed<string>(() => {
    switch (props.tone) {
        case 'success':
            return 'bg-emerald-500';
        case 'error':
            return 'bg-rose-500';
        case 'warning':
            return 'bg-amber-500';
        case 'info':
            return 'bg-blue-500';
        default: {
            const _exhaustive: never = props.tone;

            throw new Error(`Tonalité non gérée : ${_exhaustive as string}`);
        }
    }
});

const toneIcon = computed(() => {
    switch (props.tone) {
        case 'success':
            return CheckCircle2;
        case 'error':
            return XCircle;
        case 'warning':
            return AlertTriangle;
        case 'info':
            return Info;
        default: {
            const _exhaustive: never = props.tone;

            throw new Error(`Tonalité non gérée : ${_exhaustive as string}`);
        }
    }
});

const ariaRole = computed<'status' | 'alert'>(() =>
    props.tone === 'error' || props.tone === 'warning' ? 'alert' : 'status',
);

// Barre de progression : keyframe `floty-toast-progress` qui anime
// transform: scaleX(1) → scaleX(0) sur la durée du toast. On utilise
// une animation CSS (vs une transition sur width) pour éviter le
// timing dance inhérent aux transitions sur first-paint.
const showProgress = computed<boolean>(() => props.duration > 0);
</script>

<template>
    <div
        :role="ariaRole"
        aria-live="polite"
        class="relative flex w-80 items-start gap-3 overflow-hidden rounded-xl border border-slate-200 bg-white p-4 shadow-lg"
    >
        <span
            :class="[
                'flex size-8 shrink-0 items-center justify-center rounded-lg',
                chipClasses,
            ]"
            aria-hidden="true"
        >
            <component
                :is="toneIcon"
                :size="16"
                :stroke-width="1.75"
            />
        </span>
        <div class="flex min-w-0 flex-1 flex-col gap-0.5">
            <p class="text-base font-medium leading-tight text-slate-900">
                {{ title }}
            </p>
            <p
                v-if="description"
                class="text-sm leading-snug text-slate-500"
            >
                {{ description }}
            </p>
        </div>
        <button
            v-if="dismissible"
            type="button"
            aria-label="Fermer"
            class="shrink-0 rounded-md p-1 text-slate-400 transition-colors duration-[120ms] ease-out hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-100"
            @click="emit('dismiss')"
        >
            <X :size="14" :stroke-width="1.75" />
        </button>

        <span
            v-if="showProgress"
            :class="[
                'toast-progress absolute bottom-0 left-0 h-0.5 w-full origin-left',
                progressClasses,
            ]"
            :style="{
                animationDuration: `${duration}ms`,
            }"
            aria-hidden="true"
        />
    </div>
</template>

<style>
@keyframes floty-toast-progress {
    from {
        transform: scaleX(1);
    }
    to {
        transform: scaleX(0);
    }
}

.toast-progress {
    animation-name: floty-toast-progress;
    animation-timing-function: linear;
    animation-fill-mode: forwards;
}
</style>
