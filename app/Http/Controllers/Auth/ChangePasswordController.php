<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ChangePasswordAction;
use App\Data\Auth\ChangePasswordData;
use App\Exceptions\Auth\CurrentPasswordMismatchException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Workflow « Changer mon mot de passe » côté utilisateur connecté
 * (ADR-0012 rev. 1.1 · plan-remédiation Vague 1 Lot 2 D4 · F-10-006).
 *
 * Routes protégées par le middleware `auth` (group dans routes/auth.php).
 * Le `store()` capture l'exception `CurrentPasswordMismatchException` et
 * la mappe vers une `ValidationException` sur la clé `current_password`.
 */
final class ChangePasswordController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('User/Profile/ChangePassword/Index');
    }

    public function store(
        ChangePasswordData $data,
        Request $request,
        ChangePasswordAction $action,
    ): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();

        try {
            $action->execute($user, $data, (string) $request->ip());
        } catch (CurrentPasswordMismatchException $e) {
            throw ValidationException::withMessages([
                'current_password' => $e->getUserMessage(),
            ]);
        }

        return back()->with(
            'toast-success',
            'Votre mot de passe a été modifié. Les sessions actives sur vos autres appareils ont été déconnectées.',
        );
    }
}
