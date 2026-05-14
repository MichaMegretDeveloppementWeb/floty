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
 * et {@see LoginAttemptService} - ce FormRequest
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
     * Bornes `max:255` sur email et password (RFC 5321 limite email à
     * 254 caractères ; 255 est le standard défensif). Sans bornes, un
     * attaquant pourrait envoyer plusieurs Mo · soft-DoS sur la clé
     * RateLimiter (cf. {@see LoginAttemptService}) et bcrypt qui
     * tronque silencieusement à 72 bytes après avoir consommé l'overhead.
     *
     * `email:rfc` (sans `dns`) cohérent avec décision client de ne pas
     * faire de DNS check (plan-remediation Vague 1 Lot 2 décision 9).
     *
     * Cf. plan-remediation Vague 1 Lot 2 D1 (F-10-009).
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
