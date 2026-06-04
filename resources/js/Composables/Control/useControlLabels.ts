import { computed, toValue } from 'vue';
import type { MaybeRefOrGetter } from 'vue';

type EnumOption = App.Data.User.Vehicle.EnumOptionData;
type ControlDefinition = App.Data.User.Control.ControlDefinitionData;

/**
 * Shared label helpers for the regulatory controls domain (Chantier B / B1).
 * Anchor and duration-unit labels come from the backend option lists
 * (`EnumOptions::fromCases`), so the French wording lives server-side and the
 * front only maps a stored value back to its label.
 */
export function useControlLabels(
    anchorOptions: MaybeRefOrGetter<ReadonlyArray<EnumOption>>,
    durationUnitOptions: MaybeRefOrGetter<ReadonlyArray<EnumOption>>,
): {
    anchorLabel: (value: string) => string;
    unitLabel: (value: string) => string;
    echeanceSummary: (control: ControlDefinition) => string;
} {
    const anchorMap = computed<Map<string, string>>(
        () => new Map(toValue(anchorOptions).map((option) => [option.value, option.label])),
    );
    const unitMap = computed<Map<string, string>>(
        () => new Map(toValue(durationUnitOptions).map((option) => [option.value, option.label])),
    );

    const anchorLabel = (value: string): string => anchorMap.value.get(value) ?? value;
    const unitLabel = (value: string): string => unitMap.value.get(value) ?? value;

    const echeanceSummary = (control: ControlDefinition): string =>
        `Validité initiale ${control.initialDurationValue} ${unitLabel(control.initialDurationUnit)},`
        + ` puis tous les ${control.cycleValue} ${unitLabel(control.cycleUnit)}`;

    return { anchorLabel, unitLabel, echeanceSummary };
}
