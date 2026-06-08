import { AlarmClock, CircleCheck, Clock, Minus, TriangleAlert } from 'lucide-vue-next';
import { computed, toValue } from 'vue';
import type { Component, MaybeRefOrGetter } from 'vue';
import type { BadgeTone } from '@/types/ui';

type EnumOption = App.Data.User.Vehicle.EnumOptionData;

/** Minimal shape both ControlDefinitionData and EffectiveControlData satisfy. */
type EcheanceFields = {
    initialDurationValue: number;
    initialDurationUnit: string;
    cycleValue: number;
    cycleUnit: string;
};

const SCHEDULE_STATUS_LABEL: Readonly<Record<string, string>> = {
    upcoming: 'À venir',
    due_soon: 'Échéance proche',
    due_today: 'À faire aujourd\'hui',
    overdue: 'En retard',
    done_recently: 'Fait récemment',
    not_applicable: 'Non applicable',
};

const SCHEDULE_STATUS_TONE: Readonly<Record<string, BadgeTone>> = {
    upcoming: 'slate',
    due_soon: 'amber',
    due_today: 'amber',
    overdue: 'rose',
    done_recently: 'emerald',
    not_applicable: 'slate',
};

const SCHEDULE_STATUS_ICON: Readonly<Record<string, Component>> = {
    upcoming: Clock,
    due_soon: AlarmClock,
    due_today: AlarmClock,
    overdue: TriangleAlert,
    done_recently: CircleCheck,
    not_applicable: Minus,
};

const VEHICLE_STATUS_LABEL: Readonly<Record<string, string>> = {
    active: 'Actif',
    paused: 'En pause',
    disabled: 'Désactivé',
};

/**
 * Shared label helpers for the regulatory controls domain (Chantier B).
 * Anchor and duration-unit labels come from the backend option lists
 * (`EnumOptions::fromCases`), so the French wording lives server-side and the
 * front only maps a stored value back to its label. The schedule / vehicle
 * status labels and badge tones (B2) are static (the tone is not serialised).
 */
export function useControlLabels(
    anchorOptions: MaybeRefOrGetter<ReadonlyArray<EnumOption>>,
    durationUnitOptions: MaybeRefOrGetter<ReadonlyArray<EnumOption>>,
): {
    anchorLabel: (value: string) => string;
    unitLabel: (value: string) => string;
    echeanceSummary: (control: EcheanceFields) => string;
    scheduleStatusLabel: (value: string) => string;
    scheduleStatusTone: (value: string) => BadgeTone;
    scheduleStatusIcon: (value: string) => Component;
    vehicleStatusLabel: (value: string) => string;
} {
    const anchorMap = computed<Map<string, string>>(
        () => new Map(toValue(anchorOptions).map((option) => [option.value, option.label])),
    );
    const unitMap = computed<Map<string, string>>(
        () => new Map(toValue(durationUnitOptions).map((option) => [option.value, option.label])),
    );

    const anchorLabel = (value: string): string => anchorMap.value.get(value) ?? value;
    const unitLabel = (value: string): string => unitMap.value.get(value) ?? value;

    const echeanceSummary = (control: EcheanceFields): string =>
        `Validité initiale ${control.initialDurationValue} ${unitLabel(control.initialDurationUnit)},`
        + ` puis tous les ${control.cycleValue} ${unitLabel(control.cycleUnit)}`;

    const scheduleStatusLabel = (value: string): string => SCHEDULE_STATUS_LABEL[value] ?? value;
    const scheduleStatusTone = (value: string): BadgeTone => SCHEDULE_STATUS_TONE[value] ?? 'slate';
    const scheduleStatusIcon = (value: string): Component => SCHEDULE_STATUS_ICON[value] ?? Clock;
    const vehicleStatusLabel = (value: string): string => VEHICLE_STATUS_LABEL[value] ?? value;

    return {
        anchorLabel,
        unitLabel,
        echeanceSummary,
        scheduleStatusLabel,
        scheduleStatusTone,
        scheduleStatusIcon,
        vehicleStatusLabel,
    };
}
