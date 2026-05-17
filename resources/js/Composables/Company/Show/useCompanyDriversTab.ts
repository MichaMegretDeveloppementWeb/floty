import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { show as driverShowRoute } from '@/routes/user/drivers';
import { destroy as detachRoute, update as updateRoute } from '@/routes/user/drivers/memberships';

type DriverRow = App.Data.User.Company.CompanyDriverRowData;
type DriverOption = { id: number; fullName: string; initials: string };

/**
 * Logique métier de l'onglet « Conducteurs » de la page Show Company
 * (Lot 7 D01 · F-40-004 · extraction depuis `CompanyDriversTab.vue`
 * qui violait R9 + mémoire `feedback_vue_composables_extraction`).
 *
 * Concentre · état modaux (Add, Leave, Edit) · actions HTTP
 * (detach/reactivate avec confirmation via ConfirmModal Vue · plus de
 * `window.confirm()` natif F-40-018) · helpers de présentation
 * (formatDate, onRowClick) · stats dérivées (activeCount, visibleDrivers).
 *
 * **F-40-018 fix collateral** · les 2 `confirm()` natifs (detach +
 * reactivate) sont remplacés par un état `confirmAction` que le partial
 * branche sur un `ConfirmModal` unique (cohérent avec doctrine UX
 * Floty + accessibilité focus trap + cohérence design system).
 */
export type ConfirmActionKind = 'detach' | 'reactivate';

export type ConfirmAction = {
    kind: ConfirmActionKind;
    row: DriverRow;
    title: string;
    message: string;
    confirmLabel: string;
};

export type UseCompanyDriversTabOptions = {
    companyId: number;
    companyLegalName: string;
    drivers: DriverRow[];
    availableDrivers: DriverOption[];
};

export type UseCompanyDriversTabReturn = {
    showInactive: Ref<boolean>;
    showAddModal: Ref<boolean>;
    leaveDriverId: Ref<number | null>;
    leaveDriverFullName: Ref<string>;
    editRow: Ref<DriverRow | null>;
    detaching: Ref<number | null>;
    confirmAction: Ref<ConfirmAction | null>;
    activeCount: ComputedRef<number>;
    visibleDrivers: ComputedRef<DriverRow[]>;
    existingDriverIds: ComputedRef<number[]>;
    toggleShowInactive: () => void;
    openAdd: () => void;
    openLeave: (row: DriverRow) => void;
    closeLeave: () => void;
    openEdit: (row: DriverRow) => void;
    closeEdit: () => void;
    requestDetach: (row: DriverRow) => void;
    requestReactivate: (row: DriverRow) => void;
    closeConfirm: () => void;
    runConfirmAction: () => void;
    formatDate: (value: string | null) => string;
    onRowClick: (driverId: number) => void;
};

export function useCompanyDriversTab(opts: UseCompanyDriversTabOptions): UseCompanyDriversTabReturn {
    const showInactive = ref<boolean>(false);
    const showAddModal = ref<boolean>(false);
    const leaveDriverId = ref<number | null>(null);
    const leaveDriverFullName = ref<string>('');
    const editRow = ref<DriverRow | null>(null);
    const detaching = ref<number | null>(null);
    const confirmAction = ref<ConfirmAction | null>(null);

    const activeCount = computed<number>(
        () => opts.drivers.filter((d) => d.isCurrentlyActive).length,
    );

    const visibleDrivers = computed<DriverRow[]>(
        () =>
            showInactive.value
                ? opts.drivers
                : opts.drivers.filter((d) => d.isCurrentlyActive),
    );

    const existingDriverIds = computed<number[]>(() =>
        opts.drivers.filter((d) => d.isCurrentlyActive).map((d) => d.driverId),
    );

    function toggleShowInactive(): void {
        showInactive.value = !showInactive.value;
    }

    function openAdd(): void {
        showAddModal.value = true;
    }

    function openLeave(row: DriverRow): void {
        leaveDriverId.value = row.driverId;
        leaveDriverFullName.value = row.fullName;
    }

    function closeLeave(): void {
        leaveDriverId.value = null;
        leaveDriverFullName.value = '';
    }

    function openEdit(row: DriverRow): void {
        editRow.value = row;
    }

    function closeEdit(): void {
        editRow.value = null;
    }

    /**
     * F-40-018 (Lot 7 D01) · `window.confirm()` remplacé par un état
     * de confirmation que le partial branche sur `ConfirmModal`.
     *
     * Refus métier · si le driver a des contrats actifs (`contractsCount > 0`),
     * la suppression est interdite côté UI (le bouton est masqué/disable
     * avant même cet appel · garde-fou défensif ici si l'invocation
     * passe).
     */
    function requestDetach(row: DriverRow): void {
        if (row.contractsCount > 0) {
            return;
        }

        confirmAction.value = {
            kind: 'detach',
            row,
            title: 'Détacher le rattachement',
            message: `Détacher le rattachement avec ${row.fullName} ? Les dates d'entrée et de sortie seront perdues.`,
            confirmLabel: 'Détacher',
        };
    }

    function requestReactivate(row: DriverRow): void {
        confirmAction.value = {
            kind: 'reactivate',
            row,
            title: 'Réactiver le rattachement',
            message: `Réactiver le rattachement avec ${row.fullName} ? La date de sortie sera effacée.`,
            confirmLabel: 'Réactiver',
        };
    }

    function closeConfirm(): void {
        confirmAction.value = null;
    }

    function runConfirmAction(): void {
        const action = confirmAction.value;

        if (action === null) {
            return;
        }

        if (action.kind === 'detach') {
            detaching.value = action.row.pivotId;
            router.delete(detachRoute.url([action.row.driverId, action.row.pivotId]), {
                preserveScroll: true,
                onFinish: () => {
                    detaching.value = null;
                    closeConfirm();
                },
            });

            return;
        }

        // reactivate
        router.patch(
            updateRoute.url([action.row.driverId, action.row.pivotId]),
            { joined_at: action.row.joinedAt, left_at: null },
            {
                preserveScroll: true,
                onFinish: () => {
                    closeConfirm();
                },
            },
        );
    }

    function formatDate(value: string | null): string {
        if (value === null) {
            return '-';
        }

        const [y, m, d] = value.split('-');

        return `${d}/${m}/${y}`;
    }

    function onRowClick(driverId: number): void {
        router.visit(driverShowRoute.url(driverId));
    }

    return {
        showInactive,
        showAddModal,
        leaveDriverId,
        leaveDriverFullName,
        editRow,
        detaching,
        confirmAction,
        activeCount,
        visibleDrivers,
        existingDriverIds,
        toggleShowInactive,
        openAdd,
        openLeave,
        closeLeave,
        openEdit,
        closeEdit,
        requestDetach,
        requestReactivate,
        closeConfirm,
        runConfirmAction,
        formatDate,
        onRowClick,
    };
}
