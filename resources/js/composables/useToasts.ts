import { reactive, readonly } from 'vue';

type ToastTone = 'success' | 'error' | 'warning' | 'info';

export type ToastItem = {
    id: string;
    tone: ToastTone;
    title: string;
    description?: string;
    duration: number;
};

type ToastInput = {
    tone?: ToastTone;
    title: string;
    description?: string;
    duration?: number;
};

const DEFAULT_DURATION_MS = 5000;

const toasts = reactive<ToastItem[]>([]);
const timers = new Map<string, ReturnType<typeof setTimeout>>();

const generateId = (): string =>
    `toast-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;

const dismiss = (id: string): void => {
    const index = toasts.findIndex((item) => item.id === id);
    if (index === -1) return;
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
    for (const timer of timers.values()) clearTimeout(timer);
    timers.clear();
    toasts.splice(0, toasts.length);
};

export const useToasts = () => ({
    toasts: readonly(toasts),
    push,
    dismiss,
    clear,
});
