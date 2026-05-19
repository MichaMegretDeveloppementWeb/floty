<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Toasts;

use App\Support\Toasts\ToastDispatcher;
use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lot 5 D6 (F-19-007) : valide l'accumulation N messages par requête
 * sur la pile session `'toasts'`. Chaque entry porte un UUID unique
 * généré au push (utilisé côté front pour la dédup back-button).
 */
final class ToastDispatcherTest extends TestCase
{
    #[Test]
    public function push_un_toast_unique_le_persiste_en_session(): void
    {
        ToastDispatcher::success('Premier message');

        $stored = Session::get(ToastDispatcher::SESSION_KEY);

        self::assertIsArray($stored);
        self::assertCount(1, $stored);
        self::assertSame('success', $stored[0]['tone']);
        self::assertSame('Premier message', $stored[0]['message']);
        self::assertNotEmpty($stored[0]['id']);
    }

    #[Test]
    public function push_n_toasts_les_accumule_dans_la_pile(): void
    {
        // F-19-007 : le pattern Laravel natif `Session::flash` écraserait
        // le 1er message si on utilisait la même clé. Le dispatcher
        // contourne via `get + append + reflash` → 3 toasts coexistent.
        ToastDispatcher::success('3 contrats créés');
        ToastDispatcher::warning('2 déjà existants ignorés');
        ToastDispatcher::info('Période traitée · janvier 2026');

        $stored = Session::get(ToastDispatcher::SESSION_KEY);

        self::assertCount(3, $stored);
        self::assertSame('success', $stored[0]['tone']);
        self::assertSame('warning', $stored[1]['tone']);
        self::assertSame('info', $stored[2]['tone']);
    }

    #[Test]
    public function chaque_push_genere_un_id_unique(): void
    {
        // L'ID unique est consommé par le front pour dédupliquer au
        // back-button : 2 messages identiques (même tone, même contenu)
        // doivent quand même avoir 2 IDs distincts pour s'afficher.
        ToastDispatcher::success('Message identique');
        ToastDispatcher::success('Message identique');

        $stored = Session::get(ToastDispatcher::SESSION_KEY);

        self::assertCount(2, $stored);
        self::assertNotSame($stored[0]['id'], $stored[1]['id']);
    }

    #[Test]
    public function methodes_shortcuts_couvrent_les_4_tones(): void
    {
        ToastDispatcher::success('S');
        ToastDispatcher::error('E');
        ToastDispatcher::warning('W');
        ToastDispatcher::info('I');

        $tones = array_column(Session::get(ToastDispatcher::SESSION_KEY), 'tone');

        self::assertSame(['success', 'error', 'warning', 'info'], $tones);
    }
}
