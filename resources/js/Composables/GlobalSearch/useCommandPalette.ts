import { computed, onMounted, onUnmounted, reactive, readonly } from 'vue';
import type { ComputedRef, DeepReadonly } from 'vue';

/**
 * État global (singleton module-level) de la palette de recherche
 * ⌘K (V1.1). Un seul `<CommandPalette />` est monté dans `UserLayout`
 * et toute partie de l'app peut l'ouvrir via {@see open()} (ex · clic
 * sur la barre du TopBar).
 *
 * Persistance des **recents** dans `localStorage` (clé
 * `floty.search.recents`) · 5 max. Format ·
 * `{ label, sublabel, href, type, addedAt }[]`. Lecture défensive
 * (catch JSON parse, fallback []).
 *
 * Le composable expose aussi {@see useGlobalShortcut()} à appeler une
 * seule fois côté composant racine (ex · `<CommandPalette />`) pour
 * brancher le listener clavier ⌘K / Ctrl+K (mount/unmount).
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
        // localStorage indisponible (mode privé Safari, quota dépassé)
        // · on dégrade silencieusement, les recents resteront en
        // mémoire le temps de la session.
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
            // Déduplication par href · le même résultat sélectionné
            // plusieurs fois remonte en tête au lieu de doublonner.
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
 * À monter une seule fois côté `<CommandPalette />`. Branche le
 * listener clavier global ⌘K (Mac) / Ctrl+K (Windows/Linux) qui
 * toggle l'ouverture de la palette, et Escape qui ferme.
 *
 * `preventDefault` sur ⌘K pour éviter que Chrome ouvre sa propre
 * barre d'adresse.
 */
export function useCommandPaletteShortcut(): void {
    const handler = (event: KeyboardEvent): void => {
        // ⌘K (Mac) ou Ctrl+K (Windows/Linux) · toggle
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            state.isOpen = !state.isOpen;

            return;
        }

        // Escape · ferme uniquement si ouverte (sinon laisse passer
        // pour ne pas interférer avec d'autres composants)
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
