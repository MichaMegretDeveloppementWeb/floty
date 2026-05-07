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
 * Page Paramètres > Détection de risque fiscal (Phase 11 D1, ADR-0015
 * § D7 rev. 1.1). Singleton : un seul jeu de seuils pour toute
 * l'application. Update direct via le Write repo (pas d'Action : pas
 * de transaction multi-lignes, pas de side-effects, conforme R1
 * d'ADR-0013).
 */
final class FiscalRiskSettingsController extends Controller
{
    public function __construct(
        private readonly FiscalRiskSettingsReadRepositoryInterface $reader,
        private readonly FiscalRiskSettingsWriteRepositoryInterface $writer,
    ) {}

    public function edit(): Response
    {
        $settings = $this->reader->get();
        Gate::authorize('view', $settings);

        return Inertia::render('User/Settings/FiscalRisk/Index', [
            'settings' => FiscalRiskSettingsData::fromModel($settings),
        ]);
    }

    public function update(FiscalRiskSettingsData $data): RedirectResponse
    {
        Gate::authorize('update', $this->reader->get());

        $this->writer->update($data);

        return back()->with('toast-success', 'Seuils de détection enregistrés.');
    }
}
