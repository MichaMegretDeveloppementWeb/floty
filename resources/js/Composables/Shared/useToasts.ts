import { reactive, readonly } from 'vue';
import type { DeepReadonly } from 'vue';

/**
 * Global ephemeral toast stack rendered by `ToastContainer`.
 *
 * Module-level singleton store: any page or composable can push/clear without coordination.
 * Auto-dismiss is scheduled via `setTimeout` by the composable itself.
 *
 * - location: `Composables/Shared/`
 * - explicit signature: `useToasts(): UseToastsReturn`
 * - no Inertia dependency, module-level state (no Pinia in V1).
 */

export type ToastTone = 'success' | 'error' | 'warning' | 'info';

export type ToastItem = {
    id: string;
    tone: ToastTone;
    title: string;
    description?: string;
    /** Duration in ms before auto-dismiss. `0` = persistent (manual dismissal only). */
    duration: number;
};

export type ToastInput = {
    tone?: ToastTone;
    title: string;
    description?: string;
    duration?: number;
};

export type UseToastsReturn = {
    /** Read-only stack, observable from components. */
    toasts: DeepReadonly<ToastItem[]>;
    /** Pushes a toast and returns its unique id. */
    push: (input: ToastInput) => string;
    /** Removes a toast by id. */
    dismiss: (id: string) => void;
    /** Empties the stack and cancels all pending timers. */
    clear: () => void;
};

const DEFAULT_DURATION_MS = 5000;

const toasts = reactive<ToastItem[]>([]);
const timers = new Map<string, ReturnType<typeof setTimeout>>();

const generateId = (): string =>
    `toast-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;

const dismiss = (id: string): void => {
    const index = toasts.findIndex((item) => item.id === id);

    if (index === -1) {
        return;
    }

    toasts.splice(index, 1);
    const timer = timers.get(id);

    if (timer) {
        clearTimeout(timer);
        timers.delete(id);
    }
};

const push = (input: ToastInput): string => {
    const id = generateId();
    const duration = input.duration ?? DEFAULT_DURATION_MS;
    toasts.push({
        id,
        tone: input.tone ?? 'info',
        title: input.title,
        description: input.description,
        duration,
    });

    if (duration > 0) {
        timers.set(
            id,
            setTimeout(() => dismiss(id), duration),
        );
    }

    return id;
};

const clear = (): void => {
    for (const timer of timers.values()) {
        clearTimeout(timer);
    }

    timers.clear();
    toasts.splice(0, toasts.length);
};

export function useToasts(): UseToastsReturn {
    return {
        toasts: readonly(toasts),
        push,
        dismiss,
        clear,
    };
}
