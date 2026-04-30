import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useToasts } from '@/Composables/Shared/useToasts';
import type { ToastTone } from '@/Composables/Shared/useToasts';

/**
 * Pont entre les flash messages Inertia (`flash.success/error/warning/info`
 * exposés par `HandleInertiaRequests`) et la pile `useToasts()` consommée
 * par `ToastContainer`.
 *
 * Sans ce pont, les `back()->with('toast-error', '…')` côté Laravel
 * sont silencieusement perdus côté UI. À installer une seule fois dans
 * un layout englobant (`UserLayout`) pour que toute visite Inertia
 * propage automatiquement ses toasts.
 */
const TONE_TITLES: Record<ToastTone, string> = {
    success: 'Succès',
    error: 'Erreur',
    warning: 'Attention',
    info: 'Information',
};

const TONES: readonly ToastTone[] = ['success', 'error', 'warning', 'info'];

export function useFlashToasts(): void {
    const page = usePage();
    const { push } = useToasts();

    watch(
        () => page.props.flash,
        (flash) => {
            if (flash === undefined || flash === null) {
                return;
            }

            for (const tone of TONES) {
                const message = flash[tone];

                if (typeof message === 'string' && message.length > 0) {
                    push({
                        tone,
                        title: TONE_TITLES[tone],
                        description: message,
                    });
                }
            }
        },
        { immediate: true, deep: true },
    );
}
