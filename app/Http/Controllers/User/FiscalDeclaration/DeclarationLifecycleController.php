<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\FiscalDeclaration;

use App\Actions\FiscalDeclaration\DiscardDraftDeclarationAction;
use App\Actions\FiscalDeclaration\MarkDeclarationAsDeferredAction;
use App\Actions\FiscalDeclaration\RevertDeferredToDraftAction;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Http\Controllers\Controller;
use App\Models\FiscalDeclaration;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Terminal lifecycle transitions for a declaration draft (defer / revert / discard).
 */
final class DeclarationLifecycleController extends Controller
{
    /**
     * Move a draft to the `deferred` state with an optional reason.
     */
    public function markDeferred(
        FiscalDeclaration $declaration,
        Request $request,
        MarkDeclarationAsDeferredAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $declaration);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $action->execute($declaration->id, $validated['reason'] ?? null);
        } catch (DomainException $e) {
            return back()->with('toast-error', $e->getMessage());
        }

        return back()->with('toast-success', 'Déclaration mise de côté.');
    }

    /**
     * Revert a deferred declaration back to `draft` without altering its data.
     */
    public function revertDefer(
        FiscalDeclaration $declaration,
        RevertDeferredToDraftAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $declaration);

        try {
            $action->execute($declaration->id);
        } catch (DomainException $e) {
            return back()->with('toast-error', $e->getMessage());
        }

        return back()->with('toast-success', 'Mise en attente annulée · brouillon repris.');
    }

    /**
     * Discard a draft, re-activating the predecessor when the obsolescence was voluntary.
     *
     * The toast message reflects whether the draft was a true draft or
     * a deferred declaration being cleared.
     */
    public function destroy(
        FiscalDeclaration $declaration,
        DiscardDraftDeclarationAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $declaration);

        try {
            $originalStatus = $action->execute($declaration->id);
        } catch (DomainException $e) {
            return back()->with('toast-error', $e->getMessage());
        }

        $message = $originalStatus === FiscalDeclarationStatus::Deferred
            ? 'Mise en attente annulée.'
            : 'Brouillon supprimé.';

        return redirect()
            ->route('user.declarations.index')
            ->with('toast-success', $message);
    }
}
