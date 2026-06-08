import { useForm } from '@inertiajs/vue3';
import type { InertiaForm } from '@inertiajs/vue3';
import { computed, toValue } from 'vue';
import type { ComputedRef, MaybeRefOrGetter } from 'vue';
import { store as storeRoute, update as updateRoute } from '@/routes/user/controls';
import { buildInheritedRecipients } from '@/Utils/control/inheritedRecipients';
import type { InheritedRecipient } from '@/Utils/control/inheritedRecipients';

type ControlDefinition = App.Data.User.Control.ControlDefinitionData;
type ControlReminderSettings = App.Data.User.Control.ControlReminderSettingsData;
type Recipient = { name: string; email: string };

type ControlFormShape = {
    name: string;
    anchor: string;
    initial_duration_value: number | null;
    initial_duration_unit: string;
    cycle_value: number | null;
    cycle_unit: string;
    notify_driver: boolean;
    implies_unavailability: boolean;
    is_active: boolean;
    customize_reminders: boolean;
    reminder_days_before: number | null;
    reminder_on_due_day: boolean;
    reminder_repeat_every_days: number | null;
    own_recipients: Recipient[];
    excluded_default_emails: string[];
};

function blankForm(): ControlFormShape {
    return {
        name: '',
        anchor: 'first_origin_registration',
        initial_duration_value: null,
        initial_duration_unit: 'years',
        cycle_value: null,
        cycle_unit: 'years',
        notify_driver: false,
        implies_unavailability: false,
        is_active: true,
        customize_reminders: false,
        reminder_days_before: null,
        reminder_on_due_day: false,
        reminder_repeat_every_days: null,
        own_recipients: [],
        excluded_default_emails: [],
    };
}

/**
 * Editor form for a global control definition (Chantier B, domaine B).
 * Snake_case `useForm` keys match the server `SnakeCaseMapper`. Recipients are
 * split into the control's own additions (includes) and the inherited defaults
 * it removes (excludes). The inherited list is the level-0 default recipients:
 * all are included by default but removable (unified with the per-vehicle
 * editor). `seed()` re-fills the form when the editor opens, for a control
 * (edit) or blank (create).
 */
export function useControlDefinitionForm(
    getEditing: MaybeRefOrGetter<ControlDefinition | null>,
    getReminderSettings: MaybeRefOrGetter<ControlReminderSettings>,
): {
    form: InertiaForm<ControlFormShape>;
    isEditing: ComputedRef<boolean>;
    inheritedRecipients: ComputedRef<ReadonlyArray<InheritedRecipient>>;
    isInheritedIncluded: (email: string) => boolean;
    toggleInherited: (email: string) => void;
    addOwnRecipient: () => void;
    removeOwnRecipient: (index: number) => void;
    fieldError: (key: string) => string | undefined;
    seed: () => void;
    submit: (onDone: () => void) => void;
} {
    const form = useForm<ControlFormShape>(blankForm());

    const isEditing = computed<boolean>(() => toValue(getEditing) !== null);

    const reminderSettings = computed<ControlReminderSettings>(() => toValue(getReminderSettings));

    const inheritedRecipients = computed<ReadonlyArray<InheritedRecipient>>(() =>
        buildInheritedRecipients(reminderSettings.value),
    );

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

        form.name = editing.name;
        form.anchor = editing.anchor;
        form.initial_duration_value = editing.initialDurationValue;
        form.initial_duration_unit = editing.initialDurationUnit;
        form.cycle_value = editing.cycleValue;
        form.cycle_unit = editing.cycleUnit;
        form.notify_driver = editing.notifyDriver;
        form.implies_unavailability = editing.impliesUnavailability;
        form.is_active = editing.isActive;
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

        if (editing !== null) {
            form.patch(updateRoute.url(editing.id), options);
        } else {
            form.post(storeRoute.url(), options);
        }
    }

    return {
        form,
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
