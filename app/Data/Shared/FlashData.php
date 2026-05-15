<?php

declare(strict_types=1);

namespace App\Data\Shared;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Flash messages exposés en shared prop Inertia.
 *
 * **Lot 5 D6 (F-19-007 + bug back-button)** · refonte vers une **liste
 * accumulée** (`toasts: ToastEntryData[]`) qui supporte ·
 *
 *   - **Accumulation N messages par requête** · plusieurs Actions
 *     peuvent empiler via {@see App\Support\Toasts\ToastDispatcher}
 *     dans la même réponse (ex. bulk import partiel · 3 success + 2
 *     warnings).
 *   - **Dédup back-button** · chaque toast porte un `id` unique · le
 *     composable front {@see useFlashToasts} maintient un Set des IDs
 *     déjà vus pour ne pas re-pousser au retour navigateur (Inertia
 *     cache la page client-side et restaure `flash.toasts` depuis
 *     history.state).
 *
 * **Rétrocompatibilité** · les 4 anciens canaux scalaires (success,
 * error, warning, info) sont conservés pour ne pas casser les ~30
 * controllers qui utilisent encore `back()->with('toast-success', '…')`.
 * Le middleware `HandleInertiaRequests` les convertit en entries de
 * `toasts` au moment du share, en générant un ID virtuel basé sur le
 * contenu + timestamp.
 *
 * @property DataCollection<int, ToastEntryData> $toasts
 */
#[TypeScript]
final class FlashData extends Data
{
    /**
     * @param  DataCollection<int, ToastEntryData>  $toasts
     */
    public function __construct(
        public ?string $success,
        public ?string $error,
        public ?string $warning,
        public ?string $info,
        #[DataCollectionOf(ToastEntryData::class)]
        public DataCollection $toasts,
    ) {}
}
