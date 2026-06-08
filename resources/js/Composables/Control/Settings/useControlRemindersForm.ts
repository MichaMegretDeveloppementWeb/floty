import { useForm } from '@inertiajs/vue3';
import type { InertiaForm } from '@inertiajs/vue3';
import { update as updateRoute } from '@/routes/user/settings/control-reminders';

type ControlReminderSettings = App.Data.User.Control.ControlReminderSettingsData;

type RemindersFormShape = {
    days_before: number;
    remind_on_due_day: boolean;
    repeat_every_days: number;
    default_recipients: { name: string; email: string }[];
};

/**
 * Form state + recipient-list mutations for the global control reminder
 * settings page (Chantier B / B1, domaine A). Snake_case `useForm` keys match
 * the Spatie `SnakeCaseMapper` server-side so validation errors map straight to
 * the fields.
 */
export function useControlRemindersForm(settings: ControlReminderSettings): {
    form: InertiaForm<RemindersFormShape>;
    addRecipient: () => void;
    removeRecipient: (index: number) => void;
    fieldError: (key: string) => string | undefined;
    submit: () => void;
} {
    const form = useForm<RemindersFormShape>({
        days_before: settings.daysBefore,
        remind_on_due_day: settings.remindOnDueDay,
        repeat_every_days: settings.repeatEveryDays,
        default_recipients: settings.defaultRecipients.map((recipient) => ({
            name: recipient.name,
            email: recipient.email,
        })),
    });

    function addRecipient(): void {
        form.default_recipients.push({ name: '', email: '' });
    }

    function removeRecipient(index: number): void {
        form.default_recipients.splice(index, 1);
    }

    function fieldError(key: string): string | undefined {
        return (form.errors as Record<string, string | undefined>)[key];
    }

    function submit(): void {
        form.post(updateRoute.url(), { preserveScroll: true });
    }

    return { form, addRecipient, removeRecipient, fieldError, submit };
}
