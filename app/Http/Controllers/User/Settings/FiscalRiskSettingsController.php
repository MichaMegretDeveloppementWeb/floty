<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Settings;

use App\Contracts\Repositories\User\FiscalRiskSettings\FiscalRiskSettingsReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalRiskSettings\FiscalRiskSettingsWriteRepositoryInterface;
use App\Data\User\FiscalRiskSettings\FiscalRiskSettingsData;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fiscal risk detection settings (singleton row, no Action wrapper because
 * the update is single-row with no side effects, per ADR-0013 R1).
 */
final class FiscalRiskSettingsController extends Controller
{
    public function __construct(
        private readonly FiscalRiskSettingsReadRepositoryInterface $reader,
        private readonly FiscalRiskSettingsWriteRepositoryInterface $writer,
    ) {}

    /**
     * Render the risk detection settings form.
     */
    public function edit(): Response
    {
        $settings = $this->reader->get();
        Gate::authorize('view', $settings);

        return Inertia::render('User/Settings/FiscalRisk/Index', [
            'settings' => FiscalRiskSettingsData::fromModel($settings),
        ]);
    }

    /**
     * Persist the new risk thresholds.
     */
    public function update(FiscalRiskSettingsData $data): RedirectResponse
    {
        Gate::authorize('update', $this->reader->get());

        $this->writer->update($data);

        return back()->with('toast-success', 'Seuils de détection enregistrés.');
    }
}
