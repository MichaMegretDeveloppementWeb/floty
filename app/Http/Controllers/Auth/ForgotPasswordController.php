<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SendPasswordResetLinkAction;
use App\Data\Auth\ForgotPasswordData;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Workflow « Mot de passe oublié » · point d'entrée du flow reset
 * (ADR-0012 rev. 1.1 · plan-remédiation Vague 1 Lot 2 D4 · F-10-006).
 *
 * Le `store()` retourne TOUJOURS un toast générique succès quel que soit
 * le résultat de `Password::sendResetLink()` (anti email enumeration).
 * La logique d'envoi vit dans {@see SendPasswordResetLinkAction} qui
 * filtre le statut avant le retour.
 */
final class ForgotPasswordController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/ForgotPassword/Index');
    }

    public function store(
        ForgotPasswordData $data,
        Request $request,
        SendPasswordResetLinkAction $action,
    ): RedirectResponse {
        $action->execute($data->email, (string) $request->ip());

        return back()->with(
            'toast-success',
            'Si l\'adresse correspond à un compte, un e-mail de récupération a été envoyé.',
        );
    }
}
