<?php

declare(strict_types=1);

namespace App\Support\Toasts;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * Stacks several toasts within the same request. Laravel's
 * `Session::flash('toast-success', $msg)` only stores a single scalar
 * per key, so two successive calls with the same key overwrite each
 * other; this dispatcher accumulates entries in a single list under
 * `toasts` so multiple toasts of any tone can coexist.
 *
 * Usage:
 * ```php
 * ToastDispatcher::success('3 contrats créés.');
 * ToastDispatcher::warning('2 déjà existants ignorés.');
 *
 * return back();
 * ```
 *
 * The legacy `back()->with('toast-success', '…')` pattern keeps working;
 * the Inertia middleware reads both channels and merges them.
 *
 * Each entry carries a UUID so the frontend can deduplicate on back/forward
 * navigation, which would otherwise restore the toast from Inertia's
 * cached `history.state`.
 */
final class ToastDispatcher
{
    public const SESSION_KEY = 'toasts';

    /**
     * Push a toast of the given tone onto the accumulated stack.
     */
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

    /**
     * Push a success toast.
     */
    public static function success(string $message): void
    {
        self::push('success', $message);
    }

    /**
     * Push an error toast.
     */
    public static function error(string $message): void
    {
        self::push('error', $message);
    }

    /**
     * Push a warning toast.
     */
    public static function warning(string $message): void
    {
        self::push('warning', $message);
    }

    /**
     * Push an informational toast.
     */
    public static function info(string $message): void
    {
        self::push('info', $message);
    }
}
