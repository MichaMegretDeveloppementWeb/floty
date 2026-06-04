<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Settings;

use App\Contracts\Repositories\User\Control\ControlReminderSettingsReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlReminderSettingsWriteRepositoryInterface;
use App\Data\User\Control\ControlReminderSettingsData;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Global reminder settings for regulatory controls (Chantier B / B1). Singleton
 * row + level-0 default recipients; no Action wrapper because the write is a
 * single atomic operation handled by the repository (ADR-0013 R1).
 */
final class ControlReminderSettingsController extends Controller
{
    public function __construct(
        private readonly ControlReminderSettingsReadRepositoryInterface $reader,
        private readonly ControlReminderSettingsWriteRepositoryInterface $writer,
    ) {}

    /**
     * Render the global reminder settings form.
     */
    public function edit(): Response
    {
        $settings = $this->reader->get();
        Gate::authorize('view', $settings);

        return Inertia::render('User/Settings/ControlReminders/Index', [
            'settings' => ControlReminderSettingsData::fromModel(
                $settings,
                $this->reader->defaultRecipients(),
            ),
        ]);
    }

    /**
     * Persist the reminder cycle defaults and re-sync the default recipients.
     */
    public function update(ControlReminderSettingsData $data): RedirectResponse
    {
        Gate::authorize('update', $this->reader->get());

        $this->writer->update($data);

        return back()->with('toast-success', 'Paramètres des rappels enregistrés.');
    }
}
