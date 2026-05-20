<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Data\Auth\CurrentUserData;
use App\Data\Shared\FlashData;
use App\Data\Shared\ToastEntryData;
use App\Data\User\Invoice\BulkInvoiceGenerationReportData;
use App\Support\Toasts\ToastDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;

/**
 * Inertia shared props exposed on every page of Floty.
 *
 * All props are strictly typed on the TypeScript side via Spatie Data
 * DTOs (auto-generated into `resources/js/types/generated/generated.d.ts`).
 *
 * Invariants:
 *   - `appName` · non-empty string, stable for the deployment lifetime.
 *   - `auth.user` · `null` until a user is authenticated, otherwise a
 *     {@see CurrentUserData}.
 *   - `flash` · four independent channels matching the four toast tones
 *     of the design system (success / error / warning / info). The
 *     controller writes via `->with('toast-success', '...')`; the
 *     frontend reads `flash.success`.
 */
final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * Return the Inertia version of the application assets.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Build the shared props payload for every Inertia response.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'appName' => config('app.name'),

            'auth' => [
                'user' => fn (): ?CurrentUserData => $this->resolveAuthenticatedUser($request),
            ],

            'flash' => fn (): FlashData => $this->buildFlashData($request),

            'bulkInvoiceReport' => fn (): ?BulkInvoiceGenerationReportData => $this->resolveBulkInvoiceReport($request),
        ];
    }

    /**
     * Resolve the bulk-invoice generation report from the flash session.
     * The payload only lives across one render after a bulk invoice
     * generation; everywhere else it is `null`. Distinct from
     * `flash.toasts` because the payload is structured (lines, annex
     * numbers) and is rendered as a detailed inline summary.
     */
    private function resolveBulkInvoiceReport(Request $request): ?BulkInvoiceGenerationReportData
    {
        $payload = $request->session()->get('bulkInvoiceReport');

        if ($payload instanceof BulkInvoiceGenerationReportData) {
            return $payload;
        }

        if (is_array($payload)) {
            return BulkInvoiceGenerationReportData::from($payload);
        }

        return null;
    }

    /**
     * Merge the four legacy scalar flash channels (kept for backward
     * compatibility with the existing controllers) and the accumulated
     * stack pushed by {@see ToastDispatcher} into a single typed list
     * `flash.toasts: ToastEntryData[]`. Every entry carries a unique id
     * so the frontend can deduplicate on browser back/forward.
     */
    private function buildFlashData(Request $request): FlashData
    {
        $session = $request->session();

        $success = $session->get('toast-success');
        $error = $session->get('toast-error');
        $warning = $session->get('toast-warning');
        $info = $session->get('toast-info');

        $toasts = [];

        foreach (['success' => $success, 'error' => $error, 'warning' => $warning, 'info' => $info] as $tone => $message) {
            if ($message !== null && $message !== '') {
                $toasts[] = new ToastEntryData(
                    id: (string) Str::uuid(),
                    tone: $tone,
                    message: $message,
                );
            }
        }

        /** @var list<array{id: string, tone: string, message: string}> $accumulated */
        $accumulated = $session->get(ToastDispatcher::SESSION_KEY, []);
        foreach ($accumulated as $entry) {
            $toasts[] = new ToastEntryData(
                id: $entry['id'],
                tone: $entry['tone'],
                message: $entry['message'],
            );
        }

        return new FlashData(
            success: $success,
            error: $error,
            warning: $warning,
            info: $info,
            toasts: $toasts,
        );
    }

    /**
     * Resolve the currently authenticated user as a {@see CurrentUserData}.
     */
    private function resolveAuthenticatedUser(Request $request): ?CurrentUserData
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return CurrentUserData::fromUser($user);
    }
}
