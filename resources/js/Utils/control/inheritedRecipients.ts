type ControlReminderSettings = App.Data.User.Control.ControlReminderSettingsData;

export type InheritedRecipient = { name: string; email: string; isAlwaysNotify: boolean };

function normalize(email: string): string {
    return email.trim().toLowerCase();
}

/**
 * Whether an email is the settings "always notify" recipient (case-insensitive).
 */
export function isAlwaysNotifyEmail(settings: ControlReminderSettings, email: string): boolean {
    const always = normalize(settings.alwaysNotifyEmail ?? '');

    return always !== '' && normalize(email) === always;
}

/**
 * The inherited recipient list shown (and toggleable) in the control editors:
 * the settings "always notify" recipient (flagged) first, then the level-0
 * default recipients, deduped on the always-notify email. Used by the global
 * control editor and the per-vehicle editor for a new specific control, so both
 * stay consistent (default-on but removable).
 */
export function buildInheritedRecipients(settings: ControlReminderSettings): InheritedRecipient[] {
    const alwaysEmail = normalize(settings.alwaysNotifyEmail ?? '');
    const list: InheritedRecipient[] = [];

    if (alwaysEmail !== '') {
        list.push({
            name: settings.alwaysNotifyName ?? alwaysEmail,
            email: alwaysEmail,
            isAlwaysNotify: true,
        });
    }

    for (const recipient of settings.defaultRecipients) {
        if (isAlwaysNotifyEmail(settings, recipient.email)) {
            continue;
        }

        list.push({ name: recipient.name, email: recipient.email, isAlwaysNotify: false });
    }

    return list;
}
