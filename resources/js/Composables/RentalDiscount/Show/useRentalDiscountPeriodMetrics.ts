/**
 * Dérive les métriques de période d'une réduction commerciale pour le
 * tile « Avancement » de la page Show (refonte Mercury).
 *
 * Renvoie un breakdown stable et purement dérivable de start/end + today ·
 * `bigValue` est le chiffre affiché en grand (% si active, jours sinon),
 * `progressPercent` alimente la barre, `timelineLeft/Center/Right` sont
 * les 3 légendes sous la barre.
 *
 * Pas d'IO, pas de réactivité externe · helper pur testable.
 */
import { computed } from 'vue';
import type { ComputedRef } from 'vue';

type Status = 'planned' | 'active' | 'expired';

type PeriodMetrics = {
    /** Label métier au-dessus du gros chiffre. */
    label: string;
    /** Chiffre principal · "52 %" si active, "12 j" si planned/expired. */
    bigValue: string;
    /** Petite phrase descriptive sous le big number. */
    meta: string;
    /** 0..100 pour la barre de progression. */
    progressPercent: number;
    /** 3 légendes sous la barre (gauche · centre · droite). */
    timelineLeft: string;
    timelineCenter: string;
    timelineRight: string;
};

function parseLocalDate(iso: string): Date {
    const [y, m, d] = iso.split('-').map(Number);

    return new Date(y!, m! - 1, d!);
}

function diffDaysInclusive(fromISO: string, toISO: string): number {
    const from = parseLocalDate(fromISO);
    const to = parseLocalDate(toISO);
    const diffMs = to.getTime() - from.getTime();

    return Math.floor(diffMs / (1000 * 60 * 60 * 24)) + 1;
}

function todayISO(): string {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

function formatDateShortFr(iso: string): string {
    const [, m, d] = iso.split('-');

    return `${d}/${m}`;
}

export function useRentalDiscountPeriodMetrics(
    startDate: () => string,
    endDate: () => string,
    status: () => Status,
): ComputedRef<PeriodMetrics> {
    return computed<PeriodMetrics>(() => {
        const start = startDate();
        const end = endDate();
        const today = todayISO();
        const totalDays = diffDaysInclusive(start, end);
        const currentStatus = status();

        if (currentStatus === 'planned') {
            const daysUntilStart = diffDaysInclusive(today, start) - 1;

            return {
                label: 'Avancement',
                bigValue: '0 %',
                meta: `Commence dans ${daysUntilStart} jour${daysUntilStart > 1 ? 's' : ''} · durée totale ${totalDays} jour${totalDays > 1 ? 's' : ''}`,
                progressPercent: 0,
                timelineLeft: formatDateShortFr(start),
                timelineCenter: '',
                timelineRight: formatDateShortFr(end),
            };
        }

        if (currentStatus === 'expired') {
            const daysSinceEnd = diffDaysInclusive(end, today) - 1;

            return {
                label: 'Avancement',
                bigValue: '100 %',
                meta: `Terminée depuis ${daysSinceEnd} jour${daysSinceEnd > 1 ? 's' : ''} · durée totale ${totalDays} jour${totalDays > 1 ? 's' : ''}`,
                progressPercent: 100,
                timelineLeft: formatDateShortFr(start),
                timelineCenter: '',
                timelineRight: formatDateShortFr(end),
            };
        }

        const daysElapsed = diffDaysInclusive(start, today);
        const progress = Math.round((daysElapsed / totalDays) * 100);

        return {
            label: 'Avancement',
            bigValue: `${progress} %`,
            meta: `${daysElapsed} jour${daysElapsed > 1 ? 's' : ''} sur ${totalDays}`,
            progressPercent: progress,
            timelineLeft: formatDateShortFr(start),
            timelineCenter: "Aujourd'hui",
            timelineRight: formatDateShortFr(end),
        };
    });
}
