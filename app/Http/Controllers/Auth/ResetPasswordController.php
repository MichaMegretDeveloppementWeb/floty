<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResetPasswordAction;
use App\Data\Auth\ResetPasswordData;
use App\Exceptions\Auth\InvalidResetTokenException;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Workflow de réinitialisation de mot de passe (ADR-0012 rev. 1.1 ·
 * plan-remédiation Vague 1 Lot 2 D4 · F-10-006).
 *
 * `show()` reçoit le token via la route signée par le mail · l'email
 * est passé en query string par `Password::sendResetLink()` (cf.
 * Notification custom). Pas de validation du token côté GET ·
 * c'est `store()` qui valide via `Password::reset()`.
 */
final class ResetPasswordController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword/Index', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(
        ResetPasswordData $data,
        Request $request,
        ResetPasswordAction $action,
    ): RedirectResponse {
        try {
            $action->execute($data, (string) $request->ip());
        } catch (InvalidResetTokenException $e) {
            throw ValidationException::withMessages([
                'email' => $e->getUserMessage(),
            ]);
        }

        return redirect()->route('login')->with(
            'toast-success',
            'Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.',
        );
    }
}
