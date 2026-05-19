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
 * Password reset flow.
 *
 * The token reaches `show()` through the signed email link and is only
 * validated by `store()` via Password::reset(); the GET endpoint trusts
 * the route signature.
 */
final class ResetPasswordController extends Controller
{
    /**
     * Render the reset-password form for the given token.
     */
    public function show(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword/Index', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /**
     * Persist the new password and redirect to login.
     */
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
