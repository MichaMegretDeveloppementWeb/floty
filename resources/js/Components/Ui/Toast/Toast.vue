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
         * Display duration in ms (matches the TTL on `useToasts`). Drives
         * the bottom progress bar shrinking from 100% to 0%. 0 = persistent
         * (no bar).
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

// Single typed lookup for chip/progress/icon. `Record<ToastTone, ...>`
// guarantees exhaustiveness: TS rejects any new tone without config.
const TONE_CONFIG: Record<ToastTone, { chip: string; progress: string; icon: typeof CheckCircle2 }> = {
    success: { chip: 'bg-emerald-50 text-emerald-700', progress: 'bg-emerald-500', icon: CheckCircle2 },
    error: { chip: 'bg-rose-50 text-rose-700', progress: 'bg-rose-500', icon: XCircle },
    warning: { chip: 'bg-amber-50 text-amber-700', progress: 'bg-amber-500', icon: AlertTriangle },
    info: { chip: 'bg-blue-50 text-blue-700', progress: 'bg-blue-500', icon: Info },
};

const chipClasses = computed<string>(() => TONE_CONFIG[props.tone].chip);
const progressClasses = computed<string>(() => TONE_CONFIG[props.tone].progress);
const toneIcon = computed(() => TONE_CONFIG[props.tone].icon);

const ariaRole = computed<'status' | 'alert'>(() =>
    props.tone === 'error' || props.tone === 'warning' ? 'alert' : 'status',
);

// Progress bar driven by the `floty-toast-progress` keyframe (scaleX 1→0).
// Animation over duration rather than a width transition to avoid the
// first-paint timing dance.
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
