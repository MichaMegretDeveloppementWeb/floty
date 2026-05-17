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
 * Cycle de génération d'une déclaration fiscale (Phase 11 D4 ·
 * extrait de `DeclarationController` pour respecter R7/R10 ADR-0013 ·
 * Lot 4 D13 / F-34-105).
 *
 * Regroupe les transitions du brouillon vers la version finalisée ·
 *   - `prepare`        crée un Draft pour `(company, year)`
 *   - `storeDecision`  persiste une décision cluster pendant le review
 *   - `generate`       verrouille en `generated` + PDF
 *   - `regenerate`     crée un nouveau Draft chaîné (régénération)
 *   - `modify`         S5 → S7 volontaire (D5.10.E)
 */
final class DeclarationGenerationController extends Controller
{
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

        // Sécurité applicative : la décision doit concerner la même
        // (company, year) que la déclaration ciblée par la route.
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
     * Phase 13 D5.10.E · transition volontaire S5 → S7. Permet à
     * l'utilisateur de modifier une déclaration générée et active sans
     * attendre une mutation involontaire de périmètre. La déclaration
     * courante devient obsolète avec le motif `VoluntaryModification`,
     * un nouveau brouillon est créé et chaîné. Si l'utilisateur change
     * d'avis, `destroy` saura ré-activer la déclaration précédente.
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
