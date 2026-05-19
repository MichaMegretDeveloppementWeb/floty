import { computed, onMounted, onUnmounted, reactive, readonly } from 'vue';
import type { ComputedRef, DeepReadonly } from 'vue';

/**
 * Module-level singleton state for the ⌘K search palette.
 *
 * A single `<CommandPalette />` is mounted in `UserLayout`; anywhere in the app can open
 * it via {@see open()} (e.g. clicking the TopBar bar).
 *
 * Recents persist in `localStorage` under `floty.search.recents` (max 5).
 * Format: `{ label, sublabel, href, type, addedAt }[]`. Read defensively (JSON parse catch, fallback []).
 *
 * Also exposes {@see useCommandPaletteShortcut()} to call once on the root component
 * to wire the global ⌘K / Ctrl+K keyboard listener.
 */

const RECENTS_STORAGE_KEY = 'floty.search.recents';
const RECENTS_MAX = 5;

export type CommandPaletteRecentType =
    | 'vehicle'
    | 'company'
    | 'driver'
    | 'declaration'
    | 'contract-shortcut';

export type CommandPaletteRecent = {
    label: string;
    sublabel: string | null;
    href: string;
    type: CommandPaletteRecentType;
    addedAt: number;
};

type State = {
    isOpen: boolean;
    recents: CommandPaletteRecent[];
};

const state: State = reactive({
    isOpen: false,
    recents: loadRecents(),
});

function loadRecents(): CommandPaletteRecent[] {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const raw = window.localStorage.getItem(RECENTS_STORAGE_KEY);

        if (raw === null) {
            return [];
        }

        const parsed: unknown = JSON.parse(raw);

        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed
            .filter(isValidRecent)
            .slice(0, RECENTS_MAX);
    } catch {
        return [];
    }
}

function isValidRecent(value: unknown): value is CommandPaletteRecent {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Record<string, unknown>;

    return (
        typeof candidate.label === 'string'
        && typeof candidate.href === 'string'
        && typeof candidate.type === 'string'
        && typeof candidate.addedAt === 'number'
    );
}

function persistRecents(): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(
            RECENTS_STORAGE_KEY,
            JSON.stringify(state.recents),
        );
    } catch {
        // localStorage unavailable (Safari private mode, quota exceeded);
        // degrade silently, recents stay in memory for the session.
    }
}

export type UseCommandPaletteReturn = {
    isOpen: ComputedRef<boolean>;
    recents: DeepReadonly<CommandPaletteRecent[]>;
    open: () => void;
    close: () => void;
    toggle: () => void;
    addRecent: (item: Omit<CommandPaletteRecent, 'addedAt'>) => void;
    clearRecents: () => void;
};

const isOpenRef = computed<boolean>(() => state.isOpen);

export function useCommandPalette(): UseCommandPaletteReturn {
    return {
        isOpen: isOpenRef,
        recents: readonly(state.recents) as DeepReadonly<CommandPaletteRecent[]>,
        open(): void {
            state.isOpen = true;
        },
        close(): void {
            state.isOpen = false;
        },
        toggle(): void {
            state.isOpen = !state.isOpen;
        },
        addRecent(item): void {
            // Dedup by href: the same result selected several times moves to the top instead of duplicating.
            const filtered = state.recents.filter(
                (r) => r.href !== item.href,
            );

            state.recents.splice(0, state.recents.length, {
                ...item,
                addedAt: Date.now(),
            }, ...filtered);

            if (state.recents.length > RECENTS_MAX) {
                state.recents.length = RECENTS_MAX;
            }

            persistRecents();
        },
        clearRecents(): void {
            state.recents.length = 0;
            persistRecents();
        },
    };
}

/**
 * Mount once on `<CommandPalette />`. Wires the global ⌘K (Mac) / Ctrl+K (Win/Linux) listener
 * that toggles the palette, plus Escape to close it.
 *
 * `preventDefault` on ⌘K to avoid Chrome opening its own address bar.
 */
export function useCommandPaletteShortcut(): void {
    const handler = (event: KeyboardEvent): void => {
        // ⌘K (Mac) or Ctrl+K (Win/Linux): toggle
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            state.isOpen = !state.isOpen;

            return;
        }

        // Escape: close only if open (otherwise let it through to avoid interfering with other components)
        if (event.key === 'Escape' && state.isOpen) {
            event.preventDefault();
            state.isOpen = false;
        }
    };

    onMounted(() => {
        window.addEventListener('keydown', handler);
    });

    onUnmounted(() => {
        window.removeEventListener('keydown', handler);
    });
}
