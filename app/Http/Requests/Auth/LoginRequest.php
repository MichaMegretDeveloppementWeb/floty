<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Actions\Auth\LoginAction;
use App\Services\Auth\LoginAttemptService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation des inputs de connexion.
 *
 * La logique métier (rate-limit, tentative d'authentification, mise à
 * jour `last_login_at`) vit dans {@see LoginAction}
 * et {@see LoginAttemptService} — ce FormRequest
 * ne fait que la validation des inputs (ADR-0013 R3 : pas de logique
 * métier dans la couche HTTP).
 */
final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
