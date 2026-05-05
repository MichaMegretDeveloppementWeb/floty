<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Fiscal\AvailableYearsResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton : garantit qu'un seul resolver vit par requête HTTP
        // (cache mémoire process partagé entre tous les consumers d'une
        // même requête). Cf. chantier η Phase 0.1.
        $this->app->singleton(AvailableYearsResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDates();
        $this->configureEloquent();
        $this->configureDatabase();
        $this->configurePasswordDefaults();
    }

    /**
     * Utilise CarbonImmutable partout : aucune mutation accidentelle d'une
     * date passée par référence entre couches. La locale applicative FR est
     * propagée à toute instance Carbon produite (formatage jours/mois).
     */
    protected function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
        Carbon::setLocale(config('app.locale', 'fr'));
    }

    /**
     * Active le strict mode Eloquent en non-production :
     *   - preventLazyLoading : lève une exception à la moindre N+1.
     *   - preventAccessingMissingAttributes : lève si un code accède à un
     *     attribut qui n'a pas été sélectionné par la requête.
     *   - preventSilentlyDiscardingAttributes : lève si un fillable rejette
     *     silencieusement un attribut assignable.
     *
     * En production on relâche pour ne pas casser l'UX sur un oubli isolé,
     * les tests et la CI détectent les violations avant mise en prod.
     */
    protected function configureEloquent(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    /**
     * Interdit les commandes destructives (`migrate:fresh`, `db:wipe`, etc.)
     * en production. Seule parade si un script de déploiement mal câblé les
     * invoque malgré nous.
     */
    protected function configureDatabase(): void
    {
        DB::prohibitDestructiveCommands(
            $this->app->isProduction(),
        );
    }

    /**
     * Politique de mot de passe Floty (cf. ADR-0011) : longueur 8 minimum,
     * pas de complexité imposée. Alignement sur NIST SP 800-63B 2024 qui
     * privilégie la longueur au forçage de caractères spéciaux.
     *
     * En non-production : pas de règle appliquée pour laisser les factories
     * et les seeders générer des mots de passe courts à la volée.
     */
    protected function configurePasswordDefaults(): void
    {
        Password::defaults(
            fn (): ?Password => $this->app->isProduction()
                ? Password::min(8)->uncompromised()
                : null,
        );
    }
}
