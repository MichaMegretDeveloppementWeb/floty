<?php

declare(strict_types=1);

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTypeScriptTransformerServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;

/**
 * Configuration de la génération des types TS depuis les enums et les
 * Data classes Spatie. La sortie alimente
 * `resources/js/types/generated/generated.d.ts` (ignoré par Git, regénéré
 * à chaque `npm run build`).
 *
 * Les types générés sont consommés par les pages Vue via :
 *
 *     import type { VehicleListItemData } from '@/types/generated';
 *
 * Aucun type métier ne doit être redéclaré inline dans les composants —
 * la source de vérité est ici, côté PHP.
 */
class TypeScriptTransformerServiceProvider extends BaseTypeScriptTransformerServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $outputDirectory = resource_path('js/types/generated');
        if (! is_dir($outputDirectory)) {
            mkdir($outputDirectory, recursive: true);
        }

        $config
            ->transformer(AttributedClassTransformer::class)
            ->transformer(EnumTransformer::class)
            ->transformDirectories(
                app_path('Data'),
                app_path('Enums'),
            )
            ->outputDirectory($outputDirectory)
            ->writer(new GlobalNamespaceWriter('generated.d.ts'));
    }
}
