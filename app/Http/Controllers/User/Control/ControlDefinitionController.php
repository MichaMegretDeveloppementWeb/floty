<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Control;

use App\Actions\Control\DeleteControlDefinitionAction;
use App\Actions\Control\SaveControlDefinitionAction;
use App\Contracts\Repositories\User\Control\ControlDefinitionReadRepositoryInterface;
use App\Contracts\Repositories\User\Control\ControlReminderSettingsReadRepositoryInterface;
use App\Data\User\Control\ControlDefinitionFormData;
use App\Data\User\Control\ControlReminderSettingsData;
use App\Enums\Control\ControlAnchor;
use App\Enums\Control\DurationUnit;
use App\Http\Controllers\Controller;
use App\Models\ControlDefinition;
use App\Support\EnumOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Global regulatory controls catalog (Chantier B / B1), its own clean domain.
 * The index carries the full catalog (a small bounded set, edited inline via a
 * modal) plus the global reminder settings, so the editor can show what each
 * control inherits (reminder cycle + level-0 recipients) without an extra
 * round-trip. Creation / edition coordinate definition + recipient deltas in a
 * transaction via {@see SaveControlDefinitionAction}.
 */
final class ControlDefinitionController extends Controller
{
    public function __construct(
        private readonly ControlDefinitionReadRepositoryInterface $reader,
        private readonly ControlReminderSettingsReadRepositoryInterface $reminderReader,
    ) {}

    /**
     * List the global control definitions with the inherited reminder context.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', ControlDefinition::class);

        return Inertia::render('User/Controls/Index/Index', [
            'controls' => $this->reader->listForCatalog(),
            'reminderSettings' => ControlReminderSettingsData::fromModel(
                $this->reminderReader->get(),
                $this->reminderReader->defaultRecipients(),
            ),
            'anchorOptions' => EnumOptions::fromCases(ControlAnchor::cases()),
            'durationUnitOptions' => EnumOptions::fromCases(DurationUnit::cases()),
        ]);
    }

    /**
     * Create a global control definition.
     */
    public function store(ControlDefinitionFormData $data, SaveControlDefinitionAction $action): RedirectResponse
    {
        Gate::authorize('create', ControlDefinition::class);

        $action->execute($data);

        return back()->with('toast-success', 'Contrôle réglementaire créé.');
    }

    /**
     * Update an existing global control definition.
     */
    public function update(
        ControlDefinition $control,
        ControlDefinitionFormData $data,
        SaveControlDefinitionAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $control);

        $action->execute($data, $control);

        return back()->with('toast-success', 'Contrôle réglementaire mis à jour.');
    }

    /**
     * Soft-delete a global control definition.
     */
    public function destroy(ControlDefinition $control, DeleteControlDefinitionAction $action): RedirectResponse
    {
        Gate::authorize('delete', $control);

        $action->execute($control);

        return back()->with('toast-success', 'Contrôle réglementaire supprimé.');
    }
}
