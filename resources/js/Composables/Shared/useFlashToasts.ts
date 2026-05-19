import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useToasts } from '@/Composables/Shared/useToasts';
import type { ToastTone } from '@/Composables/Shared/useToasts';

/**
 * Bridges Inertia flash messages (`flash.toasts: ToastEntryData[]` exposed by `HandleInertiaRequests`)
 * to the `useToasts()` stack consumed by `ToastContainer`.
 *
 * - Accumulates N toasts per request: the watcher iterates `flash.toasts` and supports several messages
 *   of the same tone stacked through `ToastDispatcher`.
 * - Back-button dedup: a module-level `Set` keeps already-pushed IDs. When the user navigates back,
 *   Inertia restores `flash.toasts` from its history.state cache; the watcher re-fires but the ID is
 *   already known and is skipped. Without this, the toast would reappear on each cached visit.
 *
 * Backward-compatible: existing `back()->with('toast-success', '…')` keeps working as the Inertia
 * middleware converts them into `flash.toasts` entries on share.
 *
 * Install once in an enclosing layout (`UserLayout`) so every Inertia visit propagates its toasts automatically.
 */
const TONE_TITLES: Record<ToastTone, string> = {
    success: 'Succès',
    error: 'Erreur',
    warning: 'Attention',
    info: 'Information',
};

const seenToastIds = new Set<string>();

type FlashToastEntry = {
    id: string;
    tone: string;
    message: string;
};

const isToastTone = (value: string): value is ToastTone =>
    value === 'success' || value === 'error' || value === 'warning' || value === 'info';

export function useFlashToasts(): void {
    const page = usePage();
    const { push } = useToasts();

    watch(
        () => page.props.flash?.toasts,
        (toasts) => {
            if (!Array.isArray(toasts)) {
                return;
            }

            for (const entry of toasts as FlashToastEntry[]) {
                if (seenToastIds.has(entry.id)) {
                    continue;
                }

                seenToastIds.add(entry.id);

                if (!isToastTone(entry.tone)) {
                    continue;
                }

                push({
                    tone: entry.tone,
                    title: TONE_TITLES[entry.tone],
                    description: entry.message,
                });
            }
        },
        { immediate: true, deep: true },
    );
}
