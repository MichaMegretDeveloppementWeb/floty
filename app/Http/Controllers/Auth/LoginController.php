<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginAction;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\TooManyLoginAttemptsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class LoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/Login/Index');
    }

    public function store(LoginRequest $request, LoginAction $login): RedirectResponse
    {
        try {
            $login->execute(
                email: (string) $request->validated('email'),
                password: (string) $request->validated('password'),
                ip: (string) $request->ip(),
            );
        } catch (InvalidCredentialsException|TooManyLoginAttemptsException $e) {
            throw ValidationException::withMessages([
                'email' => $e->getUserMessage(),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('user.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
