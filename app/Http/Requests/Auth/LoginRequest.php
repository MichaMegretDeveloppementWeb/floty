<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Actions\Auth\LoginAction;
use App\Services\Auth\LoginAttemptService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the inputs of the login form.
 *
 * Business logic (rate limiting, authentication attempt, `last_login_at`
 * update) lives in {@see LoginAction} and {@see LoginAttemptService};
 * this FormRequest only validates inputs (ADR-0013 R3 — no business
 * logic on the HTTP layer).
 */
final class LoginRequest extends FormRequest
{
    /**
     * Authorise the request; login is open to anonymous users.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules.
     *
     * `max:255` bounds defend against a soft-DoS where an attacker would
     * pass several MB to inflate the bcrypt cost and the rate-limiter
     * key (bcrypt silently truncates to 72 bytes anyway). `email:rfc`
     * is used without the `dns` check by client decision.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
