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
 * Transitions terminales du cycle de vie d'une déclaration fiscale
 * (extrait de `DeclarationController` pour respecter R7/R10 ADR-0013 ·
 * Lot 4 D13 / F-34-105).
 *
 * Regroupe les actions qui sortent un brouillon de l'édition active
 * sans le finaliser ·
 *   - `markDeferred`  brouillon → `deferred` (mis en attente)
 *   - `revertDefer`   `deferred` → brouillon (reprise de l'édition,
 *                     réciproque de `markDeferred`, préserve toutes
 *                     les données associées)
 *   - `destroy`       suppression d'un brouillon (Draft ou Deferred) ·
 *                     ré-active intelligemment le predecessor si
 *                     l'obsolescence était purement volontaire
 */
final class DeclarationLifecycleController extends Controller
{
    public function markDeferred(
        FiscalDeclaration $declaration,
        Request $request,
        MarkDeclarationAsDeferredAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $declaration);

        // Lot 5 D13 · raison de mise en pause optionnelle (modal textarea
        // côté front · max 500 caractères). Persistée sur la déclaration
        // tant qu'elle reste reportée, effacée au revert / generate.
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
     * Annule la mise en attente d'une déclaration (`deferred → draft`)
     * sans toucher aux données associées (décisions de revue, chaîne
     * d'obsolescence éventuelle). Réciproque sémantique de
     * `markDeferred`.
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
     * Phase 13 D5.10.E · suppression d'un brouillon. Soft delete +
     * gestion intelligente du predecessor : ré-activation si l'obsolescence
     * était purement volontaire, simple unlink sinon.
     *
     * Lot 5 D2 · l'Action retourne le statut original du brouillon
     * (Draft ou Deferred) pour permettre un toast contextualisé · un
     * Deferred est sémantiquement « mis de côté », pas un brouillon
     * en cours d'édition, donc le message reflète l'annulation de la
     * mise en attente plutôt qu'une suppression sèche.
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
