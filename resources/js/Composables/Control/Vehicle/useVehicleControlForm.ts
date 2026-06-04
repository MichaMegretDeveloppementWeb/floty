import { useForm } from '@inertiajs/vue3';
import type { InertiaForm } from '@inertiajs/vue3';
import { computed, toValue } from 'vue';
import type { ComputedRef, MaybeRefOrGetter } from 'vue';
import { store as storeRoute, update as updateRoute } from '@/routes/user/vehicles/controls/overrides';

type EffectiveControl = App.Data.User.Control.Vehicle.EffectiveControlData;
type ControlReminderSettings = App.Data.User.Control.ControlReminderSettingsData;
type Recipient = { name: string; email: string };

type VehicleControlFormShape = {
    control_definition_id: number | null;
    status: App.Enums.Control.VehicleControlStatus;
    customize_schedule: boolean;
    name: string;
    anchor: string;
    initial_duration_value: number | null;
    initial_duration_unit: string;
    cycle_value: number | null;
    cycle_unit: string;
    customize_behaviour: boolean;
    notify_driver: boolean;
    implies_unavailability: boolean;
    customize_reminders: boolean;
    reminder_days_before: number | null;
    reminder_on_due_day: boolean;
    reminder_repeat_every_days: number | null;
    own_recipients: Recipient[];
    excluded_default_emails: string[];
};

function blankForm(): VehicleControlFormShape {
    return {
        control_definition_id: null,
        status: 'active',
        customize_schedule: true,
        name: '',
        anchor: 'first_origin_registration',
        initial_duration_value: null,
        initial_duration_unit: 'years',
        cycle_value: null,
        cycle_unit: 'years',
        customize_behaviour: true,
        notify_driver: false,
        implies_unavailability: false,
        customize_reminders: false,
        reminder_days_before: null,
        reminder_on_due_day: false,
        reminder_repeat_every_days: null,
        own_recipients: [],
        excluded_default_emails: [],
    };
}

/**
 * Editor form for a per-vehicle control (Chantier B / B2): an override of a
 * global definition (sparse, section toggles) or a new vehicle-specific control
 * (full recipe). `seed()` fills from the effective control when the editor
 * opens (null = create specific). Submit routes to store (no override yet) or
 * update (existing override).
 */
export function useVehicleControlForm(
    vehicleId: number,
    getEditing: MaybeRefOrGetter<EffectiveControl | null>,
    getReminderSettings: MaybeRefOrGetter<ControlReminderSettings>,
): {
    form: InertiaForm<VehicleControlFormShape>;
    isSpecific: ComputedRef<boolean>;
    isEditing: ComputedRef<boolean>;
    inheritedRecipients: ComputedRef<ReadonlyArray<Recipient>>;
    isInheritedIncluded: (email: string) => boolean;
    toggleInherited: (email: string) => void;
    addOwnRecipient: () => void;
    removeOwnRecipient: (index: number) => void;
    fieldError: (key: string) => string | undefined;
    seed: () => void;
    submit: (onDone: () => void) => void;
} {
    const form = useForm<VehicleControlFormShape>(blankForm());

    const isSpecific = computed<boolean>(() => form.control_definition_id === null);

    const isEditing = computed<boolean>(() => (toValue(getEditing)?.overrideId ?? null) !== null);

    const inheritedRecipients = computed<ReadonlyArray<Recipient>>(() => {
        const editing = toValue(getEditing);

        if (editing !== null) {
            return editing.inheritedRecipients.map((recipient) => ({ name: recipient.name, email: recipient.email }));
        }

        return toValue(getReminderSettings).defaultRecipients.map((recipient) => ({
            name: recipient.name,
            email: recipient.email,
        }));
    });

    function isInheritedIncluded(email: string): boolean {
        return !form.excluded_default_emails.includes(email);
    }

    function toggleInherited(email: string): void {
        if (isInheritedIncluded(email)) {
            form.excluded_default_emails.push(email);
        } else {
            form.excluded_default_emails = form.excluded_default_emails.filter((current) => current !== email);
        }
    }

    function addOwnRecipient(): void {
        form.own_recipients.push({ name: '', email: '' });
    }

    function removeOwnRecipient(index: number): void {
        form.own_recipients.splice(index, 1);
    }

    function fieldError(key: string): string | undefined {
        return (form.errors as Record<string, string | undefined>)[key];
    }

    function seed(): void {
        form.clearErrors();
        const editing = toValue(getEditing);

        if (editing === null) {
            Object.assign(form, blankForm());

            return;
        }

        form.control_definition_id = editing.definitionId;
        form.status = editing.status;
        form.customize_schedule = editing.isVehicleSpecific ? true : editing.customizeSchedule;
        form.name = editing.name;
        form.anchor = editing.anchor;
        form.initial_duration_value = editing.initialDurationValue;
        form.initial_duration_unit = editing.initialDurationUnit;
        form.cycle_value = editing.cycleValue;
        form.cycle_unit = editing.cycleUnit;
        form.customize_behaviour = editing.isVehicleSpecific ? true : editing.customizeBehaviour;
        form.notify_driver = editing.notifyDriver;
        form.implies_unavailability = editing.impliesUnavailability;
        form.customize_reminders = editing.customizeReminders;
        form.reminder_days_before = editing.reminderDaysBefore;
        form.reminder_on_due_day = editing.reminderOnDueDay ?? false;
        form.reminder_repeat_every_days = editing.reminderRepeatEveryDays;
        form.own_recipients = editing.ownRecipients.map((recipient) => ({
            name: recipient.name,
            email: recipient.email,
        }));
        form.excluded_default_emails = [...editing.excludedDefaultEmails];
    }

    function submit(onDone: () => void): void {
        const editing = toValue(getEditing);
        const options = {
            preserveScroll: true,
            onSuccess: (): void => onDone(),
        };

        if (editing !== null && editing.overrideId !== null) {
            form.patch(updateRoute.url({ vehicle: vehicleId, override: editing.overrideId }), options);
        } else {
            form.post(storeRoute.url(vehicleId), options);
        }
    }

    return {
        form,
        isSpecific,
        isEditing,
        inheritedRecipients,
        isInheritedIncluded,
        toggleInherited,
        addOwnRecipient,
        removeOwnRecipient,
        fieldError,
        seed,
        submit,
    };
}
