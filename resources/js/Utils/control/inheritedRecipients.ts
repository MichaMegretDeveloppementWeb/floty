type ControlReminderSettings = App.Data.User.Control.ControlReminderSettingsData;

export type InheritedRecipient = { name: string; email: string };

/**
 * The inherited recipient list shown (and toggleable) in the control editors:
 * the level-0 default recipients. Used by the global control editor and by the
 * per-vehicle editor for a new specific control, so both stay consistent
 * (included by default but removable).
 */
export function buildInheritedRecipients(settings: ControlReminderSettings): InheritedRecipient[] {
    return settings.defaultRecipients.map((recipient) => ({
        name: recipient.name,
        email: recipient.email,
    }));
}
