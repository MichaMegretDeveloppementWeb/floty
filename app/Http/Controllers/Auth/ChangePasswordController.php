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
 * Authenticated change-password flow.
 *
 * A {@see CurrentPasswordMismatchException} from the action is mapped to
 * a validation error on the `current_password` key.
 */
final class ChangePasswordController extends Controller
{
    /**
     * Render the change-password form.
     */
    public function show(): Response
    {
        return Inertia::render('User/Profile/ChangePassword/Index');
    }

    /**
     * Update the authenticated user's password.
     */
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
