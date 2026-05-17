import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useToasts } from '@/Composables/Shared/useToasts';
import type { ToastTone } from '@/Composables/Shared/useToasts';

/**
 * Pont entre les flash messages Inertia (`flash.toasts: ToastEntryData[]`
 * exposé par `HandleInertiaRequests`) et la pile `useToasts()` consommée
 * par `ToastContainer`.
 *
 * **Lot 5 D6 (F-19-007 + bug back-button)** · refonte ·
 *
 *   - **Accumulation N toasts par requête** · le watcher itère sur la
 *     liste `flash.toasts` (au lieu des 4 canaux scalaires) · supporte
 *     plusieurs messages du même tone empilés via `ToastDispatcher`.
 *
 *   - **Dédup back-button** · un `Set` module-level garde les IDs déjà
 *     poussés vers `useToasts`. Quand l'utilisateur revient sur une
 *     page via le bouton retour navigateur, Inertia restaure
 *     `flash.toasts` depuis son cache history.state · le watcher
 *     redéclenche mais l'ID est déjà connu → skip. Sans ce filet, le
 *     toast réapparaît à chaque visite cached.
 *
 * **Rétrocompatibilité** · les `back()->with('toast-success', '…')`
 * existants continuent de fonctionner · le middleware Inertia les
 * convertit en entries de `flash.toasts` au moment du share.
 *
 * À installer une seule fois dans un layout englobant (`UserLayout`)
 * pour que toute visite Inertia propage automatiquement ses toasts.
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
