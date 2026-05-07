<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Settings;

use App\Contracts\Repositories\User\Billing\BillingSettingsReadRepositoryInterface;
use App\Contracts\Repositories\User\Billing\BillingSettingsWriteRepositoryInterface;
use App\Data\User\Billing\BillingSettingsData;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page Paramètres > Facturation (Phase 14.G V1.2). Singleton · un seul
 * émetteur de facture pour toute l'application. Update direct via le
 * Write repo (pas d'Action : pas de transaction multi-lignes, pas de
 * side-effects, conforme R1 d'ADR-0013).
 */
final class BillingSettingsController extends Controller
{
    public function __construct(
        private readonly BillingSettingsReadRepositoryInterface $reader,
        private readonly BillingSettingsWriteRepositoryInterface $writer,
    ) {}

    public function edit(): Response
    {
        $settings = $this->reader->get();
        Gate::authorize('view', $settings);

        return Inertia::render('User/Settings/Billing/Index', [
            'settings' => BillingSettingsData::fromModel($settings),
        ]);
    }

    public function update(BillingSettingsData $data): RedirectResponse
    {
        Gate::authorize('update', $this->reader->get());

        $this->writer->update($data);

        return back()->with('toast-success', 'Paramètres facturation enregistrés.');
    }
}
