import { reactive, readonly, type DeepReadonly } from 'vue';

/**
 * Pile globale de toasts éphémères affichée par `ToastContainer`.
 *
 * Le store est mutualisé au module (instance unique pour toute
 * l'application). Toute page ou composable peut empiler / vider sans
 * coordination — l'auto-dismiss programmé via `setTimeout` est pris
 * en charge par le composable lui-même.
 *
 * Conforme à `composables-services-utils.md` :
 *   - emplacement `Composables/Shared/`
 *   - signature explicite `useToasts(): UseToastsReturn`
 *   - aucune dépendance Inertia ni store Pinia (volontaire — premier
 *     composable créé avant l'install de Pinia ; sera migré quand
 *     Pinia arrivera en phase 9).
 */

export type ToastTone = 'success' | 'error' | 'warning' | 'info';

export type ToastItem = {
    id: string;
    tone: ToastTone;
    title: string;
    description?: string;
    /** Durée en ms avant auto-dismiss. `0` = persistant (manuel uniquement). */
    duration: number;
};

export type ToastInput = {
    tone?: ToastTone;
    title: string;
    description?: string;
    duration?: number;
};

export type UseToastsReturn = {
    /** Pile en lecture seule, observable depuis les composants. */
    toasts: DeepReadonly<ToastItem[]>;
    /** Empile un toast et renvoie son identifiant unique. */
    push: (input: ToastInput) => string;
    /** Retire un toast par son identifiant. */
    dismiss: (id: string) => void;
    /** Vide la pile et annule tous les timers en cours. */
    clear: () => void;
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

export function useToasts(): UseToastsReturn {
    return {
        toasts: readonly(toasts),
        push,
        dismiss,
        clear,
    };
}
