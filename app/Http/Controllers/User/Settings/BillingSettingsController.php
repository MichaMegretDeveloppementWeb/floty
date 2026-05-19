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
 * Invoice issuer settings (singleton row, no Action wrapper because the
 * update is single-row with no side effects, per ADR-0013 R1).
 */
final class BillingSettingsController extends Controller
{
    public function __construct(
        private readonly BillingSettingsReadRepositoryInterface $reader,
        private readonly BillingSettingsWriteRepositoryInterface $writer,
    ) {}

    /**
     * Render the billing settings form.
     */
    public function edit(): Response
    {
        $settings = $this->reader->get();
        Gate::authorize('view', $settings);

        return Inertia::render('User/Settings/Billing/Index', [
            'settings' => BillingSettingsData::fromModel($settings),
        ]);
    }

    /**
     * Persist the new issuer information.
     */
    public function update(BillingSettingsData $data): RedirectResponse
    {
        Gate::authorize('update', $this->reader->get());

        $this->writer->update($data);

        return back()->with('toast-success', 'Paramètres facturation enregistrés.');
    }
}
