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
 * - Back-button dedup: a module-level `Set` keeps already-pushed IDs, mirrored to `sessionStorage` so
 *   the dedup survives a hard reload within the same tab. When the user navigates back, Inertia
 *   restores `flash.toasts` from its history.state cache; the watcher re-fires but the ID is already
 *   known and is skipped. Without this, the toast would reappear on each cached visit.
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

const STORAGE_KEY = 'floty:seen-toast-ids';

// Toast ids are now unique per request (one per action), so the dedup set
// grows with every action in the session. Cap it to the most recent ids:
// back/forward dedup only needs recent history, and a JS Set preserves
// insertion order, so we drop the oldest entries past the cap.
const MAX_SEEN_TOAST_IDS = 100;

const loadSeenToastIds = (): Set<string> => {
    if (typeof window === 'undefined') {
        return new Set();
    }

    try {
        const raw = window.sessionStorage.getItem(STORAGE_KEY);

        if (raw === null) {
            return new Set();
        }

        const parsed = JSON.parse(raw) as unknown;

        if (!Array.isArray(parsed)) {
            return new Set();
        }

        return new Set(parsed.filter((id): id is string => typeof id === 'string'));
    } catch {
        return new Set();
    }
};

const persistSeenToastIds = (ids: Set<string>): void => {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify([...ids]));
    } catch {
        // sessionStorage may be unavailable (private mode quota etc.) ·
        // dedup degrades to module-level only, no user-facing failure.
    }
};

const trimSeenToastIds = (ids: Set<string>): void => {
    const overflow = ids.size - MAX_SEEN_TOAST_IDS;

    if (overflow <= 0) {
        return;
    }

    const iterator = ids.values();

    for (let i = 0; i < overflow; i++) {
        const oldest = iterator.next().value;

        if (oldest !== undefined) {
            ids.delete(oldest);
        }
    }
};

const seenToastIds = loadSeenToastIds();

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

            let added = false;

            for (const entry of toasts as FlashToastEntry[]) {
                if (seenToastIds.has(entry.id)) {
                    continue;
                }

                seenToastIds.add(entry.id);
                added = true;

                if (!isToastTone(entry.tone)) {
                    continue;
                }

                push({
                    tone: entry.tone,
                    title: TONE_TITLES[entry.tone],
                    description: entry.message,
                });
            }

            if (added) {
                trimSeenToastIds(seenToastIds);
                persistSeenToastIds(seenToastIds);
            }
        },
        { immediate: true, deep: true },
    );
}
