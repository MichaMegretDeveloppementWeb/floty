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
 * Forgot-password flow entry point.
 *
 * The store action always returns a generic success toast regardless of
 * the Password::sendResetLink() outcome (anti email enumeration). The
 * dispatching logic lives in {@see SendPasswordResetLinkAction}.
 */
final class ForgotPasswordController extends Controller
{
    /**
     * Render the forgot-password form.
     */
    public function show(): Response
    {
        return Inertia::render('Auth/ForgotPassword/Index');
    }

    /**
     * Dispatch a password reset link if the email matches an account.
     */
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
