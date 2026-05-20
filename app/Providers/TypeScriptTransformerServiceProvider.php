<?php

declare(strict_types=1);

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTypeScriptTransformerServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;

/**
 * Configures TypeScript generation from Enums and Spatie Data classes.
 *
 * Output goes to `resources/js/types/generated/generated.d.ts` (ignored by
 * Git, regenerated on every `npm run build`). Vue components consume the
 * generated types via `import type { ... } from '@/types/generated'`;
 * domain types should never be redeclared inline on the frontend · the
 * single source of truth lives in PHP.
 */
class TypeScriptTransformerServiceProvider extends BaseTypeScriptTransformerServiceProvider
{
    /**
     * Configure the transformer factory: scanned directories, output
     * directory and writer.
     */
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
