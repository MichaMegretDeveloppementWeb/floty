<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\FiscalDeclaration;

use App\Actions\FiscalDeclaration\CreateDraftDeclarationAction;
use App\Actions\FiscalDeclaration\GenerateDeclarationAction;
use App\Actions\FiscalDeclaration\ModifyGeneratedDeclarationAction;
use App\Actions\FiscalDeclaration\RegenerateDeclarationAction;
use App\Actions\FiscalDeclaration\StoreReviewDecisionAction;
use App\Data\User\FiscalDeclaration\PrepareDeclarationData;
use App\Data\User\FiscalReviewDecision\StoreReviewDecisionData;
use App\Http\Controllers\Controller;
use App\Models\FiscalDeclaration;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Transitions that drive a declaration from draft to a generated version.
 */
final class DeclarationGenerationController extends Controller
{
    /**
     * Create a new draft for the (company, year) pair.
     */
    public function prepare(
        PrepareDeclarationData $data,
        CreateDraftDeclarationAction $action,
    ): RedirectResponse {
        Gate::authorize('create', FiscalDeclaration::class);

        try {
            $declaration = $action->execute($data->companyId, $data->fiscalYear);
        } catch (DomainException $e) {
            return back()->with('toast-error', $e->getMessage());
        }

        return redirect()
            ->route('user.declarations.show', ['declaration' => $declaration->id])
            ->with('toast-success', sprintf(
                'Déclaration %d préparée. Décidez chaque cluster avant de générer.',
                $data->fiscalYear,
            ));
    }

    /**
     * Persist a cluster decision recorded during the interactive review.
     */
    public function storeDecision(
        StoreReviewDecisionData $data,
        Request $request,
        FiscalDeclaration $declaration,
        StoreReviewDecisionAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $declaration);

        $user = $request->user();
        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        // Defense in depth: the decision must target the same (company, year)
        // as the declaration referenced by the route.
        if (
            $data->companyId !== $declaration->company_id
            || $data->fiscalYear !== $declaration->fiscal_year
        ) {
            return back()->with(
                'toast-error',
                'Le périmètre de la décision ne correspond pas à la déclaration.',
            );
        }

        try {
            $action->execute($data, $user->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('toast-error', $e->getMessage());
        }

        return back()->with('toast-success', 'Décision enregistrée.');
    }

    /**
     * Lock the declaration as `generated` and render the PDF.
     */
    public function generate(
        FiscalDeclaration $declaration,
        GenerateDeclarationAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $declaration);

        try {
            $generated = $action->execute($declaration->id);
        } catch (DomainException $e) {
            return back()->with('toast-error', $e->getMessage());
        }

        return redirect()
            ->route('user.declarations.show', ['declaration' => $generated->id])
            ->with('toast-success', sprintf(
                'Déclaration %s %d générée.',
                $generated->company->short_code,
                $generated->fiscal_year,
            ));
    }

    /**
     * Spawn a new draft chained to the current generated declaration.
     */
    public function regenerate(
        FiscalDeclaration $declaration,
        RegenerateDeclarationAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $declaration);

        try {
            $newDeclaration = $action->execute($declaration->id);
        } catch (DomainException $e) {
            return back()->with('toast-error', $e->getMessage());
        }

        return redirect()
            ->route('user.declarations.show', ['declaration' => $newDeclaration->id])
            ->with('toast-success', 'Nouvelle déclaration créée. Reprise des décisions par fingerprint.');
    }

    /**
     * Voluntary S5 → S7 transition: mark the generated declaration obsolete
     * and chain a fresh draft. `destroy` can later re-activate the previous
     * version if the user changes their mind.
     */
    public function modify(
        Request $request,
        FiscalDeclaration $declaration,
        ModifyGeneratedDeclarationAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $declaration);

        $user = $request->user();
        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        try {
            $newDraft = $action->execute(
                $declaration->id,
                $user->id,
                $user->full_name,
            );
        } catch (DomainException $e) {
            return back()->with('toast-error', $e->getMessage());
        }

        return redirect()
            ->route('user.declarations.show', ['declaration' => $newDraft->id])
            ->with('toast-success', sprintf(
                'Nouveau brouillon de modification créé. La déclaration %s est désormais obsolète mais reste consultable.',
                $declaration->reference ?? sprintf('#%d', $declaration->id),
            ));
    }
}
