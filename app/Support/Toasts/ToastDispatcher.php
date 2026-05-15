<?php

declare(strict_types=1);

namespace App\Support\Toasts;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * Dispatcher centralisé pour empiler N toasts dans la même requête
 * (Lot 5 D6 · F-19-007).
 *
 * **Limitation Laravel native** · `Session::flash('toast-success', $msg)`
 * écrit dans une zone session « next request » qui n'accepte qu'une
 * valeur scalaire par clé · 2 appels successifs avec la même clé
 * écrasent le premier. Pour accumuler N messages d'un même tone (ex.
 * « 3 contrats créés » + « 2 déjà existants »), on contourne via une
 * **liste accumulée** sous la clé unique `'toasts'` · chaque entrée
 * est une structure `{id, tone, message}` qui sera décodée côté
 * `HandleInertiaRequests` en `ToastEntryData[]`.
 *
 * **Pattern d'usage** ·
 * ```php
 * use App\Support\Toasts\ToastDispatcher;
 *
 * ToastDispatcher::success('3 contrats créés.');
 * ToastDispatcher::warning('2 déjà existants ignorés.');
 *
 * return back();  // Les 2 toasts seront affichés en pile côté front
 * ```
 *
 * **Rétrocompatibilité** · le pattern existant
 * `back()->with('toast-success', '…')` continue de fonctionner. Le
 * middleware Inertia lit les 4 anciens canaux ET la nouvelle pile
 * accumulée, et fusionne le tout dans `flash.toasts`. Les nouveaux
 * cas d'usage qui exigent l'accumulation passent par ce dispatcher.
 *
 * **Identifiant unique** · chaque entry porte un UUID v4. Le front
 * s'en sert pour dédupliquer · sans cet ID, un retour navigateur
 * (Inertia restaure `flash` depuis son cache history.state) déclenche
 * une re-apparition du toast.
 */
final class ToastDispatcher
{
    public const SESSION_KEY = 'toasts';

    public static function push(string $tone, string $message): void
    {
        /** @var list<array{id: string, tone: string, message: string}> $existing */
        $existing = Session::get(self::SESSION_KEY, []);
        $existing[] = [
            'id' => (string) Str::uuid(),
            'tone' => $tone,
            'message' => $message,
        ];
        Session::flash(self::SESSION_KEY, $existing);
    }

    public static function success(string $message): void
    {
        self::push('success', $message);
    }

    public static function error(string $message): void
    {
        self::push('error', $message);
    }

    public static function warning(string $message): void
    {
        self::push('warning', $message);
    }

    public static function info(string $message): void
    {
        self::push('info', $message);
    }
}
