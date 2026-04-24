# Gestion des erreurs

> **Stack référence** : Laravel 13, Inertia v3, Vue 3.5, TypeScript 6, PHP 8.5, MySQL 8.
> **Niveau d'exigence** : senior +. Aucune erreur de débutant tolérée. Aucun stack trace, nom de classe ou requête SQL ne doit jamais atteindre l'utilisateur.
> **Documents liés** : `architecture-solid.md`, `structure-fichiers.md`, `conventions-nommage.md`, `assets-vite.md`.

---

## Principe fondamental

Toute erreur possible doit être :

1. **Attrapée** au bon endroit (au moment où l'on peut la transformer ou la signaler).
2. **Loguée** avec les détails techniques nécessaires au diagnostic, dans le canal thématique adapté.
3. **Remontée** jusqu'à la couche de présentation sous forme de message humain en français.

L'utilisateur ne voit jamais de données techniques. Le développeur trouve tout dans les logs.

---

## Flux de propagation des erreurs

```
Repository  →  lève une exception typée (avec contexte technique + message utilisateur)
     ↓
Service     →  attrape uniquement si besoin d'enrichir, sinon laisse remonter
     ↓
Action      →  attrape uniquement si besoin d'enrichir, sinon laisse remonter
     ↓
Controller  →  attrape, log dans le canal adapté, redirige avec flash OU renvoie 422 Inertia
     ↓
Inertia     →  transmet flash + erreurs validation aux props Vue
     ↓
Vue         →  affiche via Toast (flash) ou via slot d'erreur (validation par champ)
```

**Règle de propagation** : chaque couche ne `catch` que si elle a quelque chose à ajouter (contexte supplémentaire, transformation de l'exception). Sinon, elle laisse l'exception remonter naturellement. **Pas de `catch (\Throwable $e) { throw $e; }` inutile**.

---

## Exceptions personnalisées

### Quand créer une exception personnalisée

- Dès qu'une erreur est spécifique à un domaine métier (pas une erreur générique PHP/Laravel).
- Dès qu'une erreur doit porter un message utilisateur distinct.
- Dès qu'on veut distinguer deux types d'échec dans le même flux (pour permettre un `catch` ciblé).

### Structure d'une exception personnalisée

Toutes les exceptions métier héritent d'une classe de base commune.

```php
namespace App\Exceptions;

use RuntimeException;

abstract class BaseAppException extends RuntimeException
{
    public function __construct(
        string $technicalMessage,
        protected readonly string $userMessage,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($technicalMessage, $code, $previous);
    }

    /** Message destiné à l'utilisateur (français, sans données techniques). */
    public function getUserMessage(): string
    {
        return $this->userMessage;
    }
}
```

### Exemples d'exceptions Floty

```php
namespace App\Exceptions\Vehicle;

use App\Exceptions\BaseAppException;

final class VehicleNotFoundException extends BaseAppException
{
    public static function byId(int $id): self
    {
        return new self(
            technicalMessage: "Vehicle not found with ID {$id}.",
            userMessage: 'Véhicule introuvable. Veuillez réessayer ou contacter le support.',
        );
    }
}
```

```php
namespace App\Exceptions\Vehicle;

use App\Enums\Vehicle\EnergySource;
use App\Exceptions\BaseAppException;
use Illuminate\Support\Carbon;

final class VehicleFiscalCharacteristicsValidationException extends BaseAppException
{
    public static function missingCo2Wltp(): self
    {
        return new self(
            technicalMessage: 'Cross-field invariant violation: homologationMethod=WLTP requires co2_wltp to be set.',
            userMessage: 'Le CO₂ WLTP est obligatoire quand la méthode d\'homologation est WLTP.',
        );
    }

    public static function missingCo2Nedc(): self
    {
        return new self(
            technicalMessage: 'Cross-field invariant violation: homologationMethod=NEDC requires co2_nedc to be set.',
            userMessage: 'Le CO₂ NEDC est obligatoire quand la méthode d\'homologation est NEDC.',
        );
    }

    public static function missingPuissanceAdmin(): self
    {
        return new self(
            technicalMessage: 'Cross-field invariant violation: homologationMethod=PA requires puissance_admin to be set.',
            userMessage: 'La puissance administrative est obligatoire quand la méthode d\'homologation est PA.',
        );
    }

    public static function hybridRequiresUnderlyingCombustionEngine(EnergySource $energySource): self
    {
        return new self(
            technicalMessage: "Cross-field invariant violation: source_energie={$energySource->value} requires type_moteur_thermique_sous_jacent to be set.",
            userMessage: 'Le type de moteur thermique sous-jacent est obligatoire pour les véhicules hybrides.',
        );
    }

    public static function effectiveFromBeforeCurrent(int $vehicleId, Carbon $newFrom, Carbon $currentFrom): self
    {
        return new self(
            technicalMessage: "Vehicle {$vehicleId}: new effective_from ({$newFrom->toDateString()}) must be strictly after current effective_from ({$currentFrom->toDateString()}).",
            userMessage: "La date d'effet de la nouvelle version doit être postérieure à celle de la version actuelle.",
        );
    }
}
```

```php
namespace App\Exceptions\Attribution;

use App\Exceptions\BaseAppException;
use Illuminate\Support\Carbon;

final class AssignmentConflictException extends BaseAppException
{
    public static function vehicleAlreadyAssigned(int $vehicleId, Carbon $date): self
    {
        return new self(
            technicalMessage: "Vehicle {$vehicleId} is already assigned on {$date->toDateString()} (UNIQUE constraint vehicles_attributions on (vehicle_id, date)).",
            userMessage: "Ce véhicule est déjà attribué à une entreprise pour cette date. Modifiez ou supprimez l'attribution existante.",
        );
    }
}
```

```php
namespace App\Exceptions\Declaration;

use App\Exceptions\BaseAppException;

final class DeclarationPdfGenerationException extends BaseAppException
{
    public static function fromRenderError(\Throwable $e, int $declarationId): self
    {
        return new self(
            technicalMessage: "Failed to render PDF for declaration {$declarationId}. Error: {$e->getMessage()}",
            userMessage: "Impossible de générer le PDF de la déclaration. Veuillez réessayer ; si le problème persiste, contactez le support.",
            previous: $e,
        );
    }

    public static function fromStorageFailure(\Throwable $e, int $declarationId, string $path): self
    {
        return new self(
            technicalMessage: "Failed to persist PDF for declaration {$declarationId} at path '{$path}'. Error: {$e->getMessage()}",
            userMessage: "Impossible d'enregistrer le PDF généré. Veuillez contacter le support.",
            previous: $e,
        );
    }
}
```

### Convention de nommage

| Pattern | Exemple | Emplacement |
|---|---|---|
| `{Entité}{Contexte}Exception` | `VehicleNotFoundException` | `app/Exceptions/Vehicle/` |
| `{Entité}{Action}Exception` | `AssignmentConflictException` | `app/Exceptions/Assignment/` |
| `{Domaine}{Contexte}Exception` | `DeclarationPdfGenerationException` | `app/Exceptions/Declaration/` |

### Factory methods statiques

Privilégier les factory methods statiques (`::byId()`, `::vehicleAlreadyAssigned()`, etc.) plutôt que `new` direct. Cela :

- Centralise la construction du message technique et du message utilisateur en français.
- Rend le code appelant plus lisible (`throw VehicleNotFoundException::byId($id)`).
- Garantit la cohérence des messages.
- Documente les cas d'usage explicitement.

---

## Pattern par couche

### Repository — lève l'exception

```php
namespace App\Repositories\User\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleListReadRepositoryInterface;
use App\Exceptions\Vehicle\VehicleListException;
use App\Exceptions\Vehicle\VehicleNotFoundException;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class VehicleListReadRepository implements VehicleListReadRepositoryInterface
{
    public function findById(int $id): Vehicle
    {
        try {
            return Vehicle::findOrFail($id);
        } catch (ModelNotFoundException) {
            throw VehicleNotFoundException::byId($id);
        }
    }

    public function listActive(): Collection
    {
        try {
            return Vehicle::query()
                ->whereNull('date_exit')
                ->whereNull('deleted_at')
                ->orderBy('immatriculation')
                ->get();
        } catch (\Throwable $e) {
            throw VehicleListException::loadFailed($e);
        }
    }
}
```

### Service — enrichir ou laisser remonter

```php
final readonly class VehicleCreationService
{
    public function __construct(
        private VehicleWriteRepositoryInterface $writeRepository,
    ) {}

    public function create(VehicleData $data): Vehicle
    {
        // Pas de try/catch : si le repository lève l'exception métier
        // adéquate, on laisse simplement remonter. Aucun enrichissement à faire.
        return $this->writeRepository->create($data->toArray());
    }
}
```

```php
// Cas où le service doit enrichir l'erreur
final readonly class DeclarationCalculationService
{
    public function calculate(Declaration $declaration): DeclarationCalculationResult
    {
        try {
            $assignments = $this->attributionReadRepository->listForDeclaration($declaration);
            return $this->pipeline->execute($assignments, $declaration);
        } catch (AssignmentListException $e) {
            throw DeclarationCalculationException::attributionsUnavailable($declaration->id, $e);
        }
    }
}
```

### Action — enrichir ou laisser remonter

Même principe que le service. L'Action n'attrape que si elle doit enrichir le contexte. Si le service ou le repository ont déjà levé la bonne exception avec le bon message utilisateur, **laisser remonter**.

### Controller Inertia — attraper, loguer, répondre

C'est la **dernière ligne de défense**. Ici on `catch`, on `Log`, et on répond via Inertia.

#### Pattern « mutation » (POST/PUT/DELETE) — flash + redirect

```php
namespace App\Http\Controllers\User\Vehicle;

use App\Actions\User\Vehicle\CreateVehicleAction;
use App\Data\User\Vehicle\VehicleData;
use App\Exceptions\BaseAppException;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Vehicle\StoreVehicleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

final class VehicleController extends Controller
{
    public function store(
        StoreVehicleRequest $request,
        CreateVehicleAction $action,
    ): RedirectResponse {
        try {
            $vehicle = $action->execute(VehicleData::from($request->validated()));
        } catch (BaseAppException $e) {
            Log::channel('vehicles')->error($e->getMessage(), [
                'exception' => $e,
                'user_id' => auth()->id(),
                'input' => $request->safe()->except(['photo']),
            ]);

            return back()
                ->withInput()
                ->with('toast-error', $e->getUserMessage());
        }

        return redirect()
            ->route('user.vehicles.show', ['vehicle' => $vehicle->id])
            ->with('toast-success', 'Véhicule enregistré.');
    }
}
```

#### Pattern « page » (GET, Inertia::render) — flash en cas d'erreur

```php
public function index(VehicleListReadRepository $repository): \Inertia\Response|RedirectResponse
{
    try {
        $vehicles = $repository->listActive();
    } catch (BaseAppException $e) {
        Log::channel('vehicles')->error($e->getMessage(), ['exception' => $e]);

        return redirect()
            ->route('user.dashboard')
            ->with('toast-error', $e->getUserMessage());
    }

    return Inertia::render('User/Vehicles/Index/Index', [
        'vehicles' => VehicleListItemData::collect($vehicles),
        'fiscalYear' => current_fiscal_year(),
    ]);
}
```

> **Pattern senior** : pour les erreurs de chargement (lecture), le bon réflexe est généralement de **rediriger** vers une page « parent » (dashboard, liste précédente) plutôt que de rester sur la page cassée. L'utilisateur garde un point d'ancrage.

---

## Côté Vue — affichage des erreurs

Trois canaux distincts d'affichage des erreurs côté Vue, chacun adapté à un type :

| Type d'erreur | Mécanisme Inertia | Affichage Vue |
|---|---|---|
| **Validation de champ** (FormRequest) | `errors` partagés Inertia, alimentés par `useForm` | Slot d'erreur sous le champ concerné |
| **Erreur métier sur action** (`BaseAppException`) | Flash session via `with('toast-error', ...)` | **Toast** éphémère |
| **Succès d'action** | Flash session via `with('toast-success', ...)` | **Toast** éphémère |

### Validation de champ — `useForm` Inertia

Inertia v3 alimente automatiquement `form.errors` à partir des erreurs validation Laravel (réponse 422). Le composant Vue affiche via slot.

```vue
<!-- resources/js/Pages/User/Vehicles/Create/Partials/VehicleForm.vue -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import TextInput from '@/Components/Ui/Input/TextInput.vue'
import InputError from '@/Components/Ui/Input/InputError.vue'
import Button from '@/Components/Ui/Button/Button.vue'

import VehicleController from '@/actions/App/Http/Controllers/User/VehicleController'

const form = useForm({
  licensePlate: '',
  brand: '',
  model: '',
})

const submit = (): void => {
  form.submit(VehicleController.store(), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  })
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-4">
    <div>
      <TextInput
        v-model="form.licensePlate"
        label="Immatriculation"
        :invalid="!!form.errors.licensePlate"
      />
      <InputError :message="form.errors.licensePlate" />
    </div>

    <div>
      <TextInput
        v-model="form.brand"
        label="Marque"
        :invalid="!!form.errors.brand"
      />
      <InputError :message="form.errors.brand" />
    </div>

    <Button type="submit" :disabled="form.processing">
      Enregistrer
    </Button>
  </form>
</template>
```

```vue
<!-- resources/js/Components/Ui/Input/InputError.vue -->
<script setup lang="ts">
defineProps<{ message?: string }>()
</script>

<template>
  <p v-if="message" class="mt-1.5 text-sm text-error">{{ message }}</p>
</template>
```

### Toast notifications via flash Inertia

Les flash session (`toast-success`, `toast-error`, `toast-warning`, `toast-info`) sont injectés dans les **shared props** Inertia et lus côté Vue par un composant global qui affiche les toasts.

```php
// app/Http/Middleware/HandleInertiaRequests.php
namespace App\Http\Middleware;

use Inertia\Middleware;
use Illuminate\Http\Request;

final class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'flash' => [
                'success' => fn () => $request->session()->get('toast-success'),
                'error'   => fn () => $request->session()->get('toast-error'),
                'warning' => fn () => $request->session()->get('toast-warning'),
                'info'    => fn () => $request->session()->get('toast-info'),
            ],
            'auth' => [
                'user' => fn () => $request->user()
                    ? CurrentUserData::from($request->user())
                    : null,
            ],
        ]);
    }
}
```

```ts
// resources/js/types/inertia.d.ts
import type { CurrentUserData } from '@/types/generated'

declare module '@inertiajs/core' {
  interface PageProps {
    flash: {
      success: string | null
      error: string | null
      warning: string | null
      info: string | null
    }
    auth: {
      user: CurrentUserData | null
    }
  }
}
```

```vue
<!-- resources/js/Components/Ui/Toast/ToastContainer.vue (monté dans UserLayout et WebLayout) -->
<script setup lang="ts">
import { computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useToast } from '@/Composables/User/useToast'

const page = usePage()
const { pushToast } = useToast()

const flash = computed(() => page.props.flash)

watch(flash, (current) => {
  if (current.success) pushToast({ variant: 'success', message: current.success })
  if (current.error) pushToast({ variant: 'error', message: current.error })
  if (current.warning) pushToast({ variant: 'warning', message: current.warning })
  if (current.info) pushToast({ variant: 'info', message: current.info })
}, { immediate: true })
</script>

<template>
  <!-- Pile de toasts en position fixe -->
  <Teleport to="body">
    <div class="fixed bottom-4 right-4 z-50 flex flex-col gap-2">
      <!-- toasts rendus par useToast -->
    </div>
  </Teleport>
</template>
```

> Le détail du composable `useToast`, du composant `Toast.vue`, et du store optionnel pour la pile de toasts sera couvert dans `composables-services-utils.md` (étape 5.4).

---

## Comportement global des erreurs HTTP — handler Inertia

### 419 (CSRF expiré / Page Expired)

Avec Inertia v3, les requêtes XHR qui échouent en 419 doivent être interceptées **côté handler Laravel** pour retourner une réponse exploitable, et **côté Inertia client** pour proposer une rechargement propre.

#### Côté Laravel — `bootstrap/app.php`

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->respond(function (Response $response, \Throwable $e, Request $request) {
        if (! $request->header('X-Inertia')) {
            return $response;
        }

        return match ($response->getStatusCode()) {
            419 => back()->with('toast-warning', 'Votre session a expiré. Veuillez réessayer.'),
            403 => back()->with('toast-error', 'Action non autorisée.'),
            default => $response,
        };
    });
});
```

#### Côté Inertia — `resources/js/app.ts`

Inertia v3 expose des callbacks globaux `onHttpException` et `onNetworkError` (cf. upgrade guide v3) :

```ts
import { router } from '@inertiajs/vue3'

router.on('exception', (event) => {
  // Erreur HTTP non interceptée par le handler Laravel (rare avec config ci-dessus)
  console.error('Inertia HTTP exception', event.detail.error)
})

router.on('error', (event) => {
  // Erreur de validation (422) déjà gérée par useForm — pas de toast à push ici
})
```

### 403 (Accès refusé / Policy denied)

Géré dans `bootstrap/app.php` par le `respond()` ci-dessus : `back()` + toast-error « Action non autorisée ».

### 404 (Not Found) et 500 (Server Error)

Pages d'erreur **Inertia personnalisées** (cf. ci-dessous) plutôt que les pages Blade par défaut.

### 503 (Maintenance)

Quand `php artisan down` est actif, Laravel retourne 503. Page de maintenance personnalisée (`resources/views/errors/503.blade.php`, **non Inertia** car le mode maintenance désactive l'application).

---

## Pages d'erreur Inertia (404, 500)

Inertia v3 supporte le rendu des pages d'erreur via Inertia plutôt que via Blade. Cela offre une **expérience cohérente** avec le reste de l'app (même layout, même design system).

### Configuration `bootstrap/app.php`

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->respond(function (Response $response, \Throwable $e, Request $request) {
        if (! app()->environment(['local', 'testing']) && in_array($response->getStatusCode(), [404, 500, 503], true)) {
            return inertia('Errors/Error', [
                'status' => $response->getStatusCode(),
            ])->toResponse($request)->setStatusCode($response->getStatusCode());
        }

        return $response;
    });
});
```

### Composant `Errors/Error.vue`

```vue
<!-- resources/js/Pages/Errors/Error.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import WebLayout from '@/Components/Layouts/WebLayout.vue'

defineOptions({ layout: WebLayout })

const props = defineProps<{ status: number }>()

const messages = {
  404: { title: 'Page introuvable', detail: 'Cette page n\'existe pas ou a été déplacée.' },
  500: { title: 'Erreur serveur', detail: 'Une erreur est survenue. L\'équipe technique a été notifiée.' },
  503: { title: 'Maintenance en cours', detail: 'Floty est temporairement indisponible. Réessayez dans quelques minutes.' },
} as const

const message = computed(() => messages[props.status as keyof typeof messages] ?? messages[500])
</script>

<template>
  <div class="min-h-screen flex flex-col items-center justify-center p-8">
    <h1 class="text-6xl font-bold text-gray-300">{{ status }}</h1>
    <p class="mt-4 text-2xl font-semibold">{{ message.title }}</p>
    <p class="mt-2 text-gray-600">{{ message.detail }}</p>
    <a href="/" class="mt-8 text-primary-600 hover:underline">Retour à l'accueil</a>
  </div>
</template>
```

---

## Logging — canaux thématiques

### Principes

1. **Logger uniquement les erreurs** — pas de logs « pour debug » en production. Chaque ligne de log doit avoir une raison d'exister.
2. **Logger au point de capture** — c'est le controller qui log, pas les couches inférieures (sauf si la couche inférieure catch et re-throw, auquel cas elle ne log pas pour éviter les doublons).
3. **Inclure le contexte technique complet** — exception originale, identifiants pertinents, données utiles au diagnostic.
4. **Jamais de données sensibles dans les logs** — pas de mots de passe, tokens, données bancaires (jamais en V1 Floty), détails médicaux. Filtrer via `$request->safe()->except([...])`.

### Structure d'un log

```php
use Illuminate\Support\Facades\Log;

Log::channel('vehicles')->error($e->getMessage(), [
    'exception' => $e,
    'user_id' => auth()->id(),
    'vehicle_id' => $vehicleId ?? null,
    'input' => $request->safe()->except(['password', 'photo']),
]);
```

### Niveaux de log

| Niveau | Usage |
|---|---|
| `Log::error()` | Erreurs qui empêchent une opération de se terminer |
| `Log::warning()` | Situations anormales mais non bloquantes (ex: cache invalidation partielle, fallback utilisé) |
| `Log::critical()` | Erreurs système graves (BDD perdue, filesystem en lecture seule, service externe down) |
| `Log::info()` / `Log::debug()` | **Interdits en production**. Tolérés ponctuellement en dev — à retirer avant merge. |

### Canaux thématiques Floty

```php
// config/logging.php
'channels' => [
    // Canal par défaut — erreurs non classifiées
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'days' => 14,
    ],

    // Canaux thématiques Floty
    'auth' => [
        'driver' => 'daily',
        'path' => storage_path('logs/auth.log'),
        'days' => 30,
    ],
    'vehicles' => [
        'driver' => 'daily',
        'path' => storage_path('logs/vehicles.log'),
        'days' => 14,
    ],
    'attributions' => [
        'driver' => 'daily',
        'path' => storage_path('logs/assignments.log'),
        'days' => 14,
    ],
    'fiscal' => [
        'driver' => 'daily',
        'path' => storage_path('logs/fiscal.log'),
        'days' => 90,                  // rétention longue : audit fiscal
    ],
    'declarations' => [
        'driver' => 'daily',
        'path' => storage_path('logs/declarations.log'),
        'days' => 365,                 // rétention 1 an : pièces justificatives officielles
    ],
    'pdf' => [
        'driver' => 'daily',
        'path' => storage_path('logs/pdf.log'),
        'days' => 30,
    ],
    'cache' => [
        'driver' => 'daily',
        'path' => storage_path('logs/cache.log'),
        'days' => 7,
    ],
],
```

### Quand créer un canal thématique

| Critère | Canal dédié ? |
|---|---|
| Domaine critique avec audit (fiscal, déclarations) | **Oui** — rétention longue indispensable |
| Authentification | **Oui** — suivi sécurité |
| Domaine avec beaucoup d'opérations (attributions, vehicles) | **Oui** — évite la pollution du log général |
| Génération de livrables (PDF) | **Oui** — diagnostic spécifique souvent nécessaire |
| Cache (invalidation, miss) | Oui en V1, à évaluer en V2 (peut être bruyant) |
| Fonctionnalité mineure avec peu d'erreurs possibles | Non — le canal par défaut suffit |

### Politique de rétention

La rétention varie par criticité :

- **365 jours** pour les déclarations (pièces justificatives officielles, à conserver pour audit fiscal).
- **90 jours** pour le moteur fiscal (suivre l'évolution des règles et leurs erreurs).
- **30 jours** pour authentification, PDF.
- **14 jours** pour les opérations courantes.
- **7 jours** pour le cache (très volumineux, peu utile au-delà).

---

## Erreurs nommées vs erreurs génériques (côté Vue)

### Principe

Quand une erreur survient sur une **action ponctuelle** (clic sur un bouton, soumission d'un formulaire), elle est affichée via un **toast** transitoire — pas besoin de la nommer côté Vue, le toast disparaît.

Quand une erreur affecte un **chargement de section** (ex: la heatmap ne peut pas se charger, mais le reste de la page reste utilisable), elle est exposée via une **prop dédiée** sur la page. La prop est typée et non générique.

```php
// Controller — Inertia render avec erreur partielle non bloquante
public function show(int $vehicleId, ...): Response
{
    $vehicle = $repository->findById($vehicleId);

    $heatmapResult = null;
    $heatmapError = null;
    try {
        $heatmapResult = $heatmapService->buildForVehicle($vehicle);
    } catch (BaseAppException $e) {
        Log::channel('vehicles')->error($e->getMessage(), ['exception' => $e]);
        $heatmapError = $e->getUserMessage();
    }

    return Inertia::render('User/Vehicles/Show/Show', [
        'vehicle' => VehicleData::from($vehicle),
        'heatmap' => $heatmapResult ? HeatmapData::from($heatmapResult) : null,
        'heatmapError' => $heatmapError,
    ]);
}
```

```vue
<!-- Vue Show.vue -->
<script setup lang="ts">
defineProps<{
  vehicle: VehicleData
  heatmap: HeatmapData | null
  heatmapError: string | null
}>()
</script>

<template>
  <div>
    <!-- ... le reste de la fiche ... -->

    <section>
      <h2>Planning</h2>
      <div v-if="heatmapError" class="rounded bg-error/10 text-error p-4">
        {{ heatmapError }}
      </div>
      <HeatmapGrid v-else-if="heatmap" :data="heatmap" />
    </section>
  </div>
</template>
```

### Convention de nommage des erreurs partielles

Les noms suivent le pattern `{domaine}Error` ou `{ressource}LoadError` :

| Nom prop | Contexte |
|---|---|
| `heatmapError` | Échec du chargement de la heatmap sur la page véhicule |
| `vehicleListError` | Échec du chargement de la liste des véhicules |
| `lcdCumulError` | Échec du calcul LCD temps réel |

---

## Validation — FormRequest

Toutes les validations passent par les **FormRequest Laravel**. Les messages sont en **français**, organisés selon le pattern Laravel.

```php
namespace App\Http\Requests\User\Vehicle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\Vehicle\VehicleUserType;
use App\Enums\Vehicle\EnergySource;

final class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Vehicle::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'immatriculation' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'immatriculation')->whereNull('deleted_at')],
            'marque' => ['required', 'string', 'max:80'],
            'modele' => ['required', 'string', 'max:120'],
            'vehicleUserType' => ['required', Rule::enum(VehicleUserType::class)],
            'energySource' => ['required', Rule::enum(EnergySource::class)],
            'acquisitionDate' => ['required', 'date', 'before_or_equal:today'],
            // … etc.
        ];
    }

    public function messages(): array
    {
        return [
            'immatriculation.required' => 'L\'immatriculation est obligatoire.',
            'immatriculation.unique' => 'Un véhicule avec cette immatriculation existe déjà.',
            'marque.required' => 'La marque est obligatoire.',
            'modele.required' => 'Le modèle est obligatoire.',
            'acquisitionDate.before_or_equal' => 'La date d\'acquisition ne peut pas être dans le futur.',
        ];
    }

    public function attributes(): array
    {
        return [
            'immatriculation' => 'immatriculation',
            'energySource' => 'source d\'énergie',
            'vehicleUserType' => 'type de véhicule',
        ];
    }
}
```

---

## Arborescence des exceptions Floty

```
app/Exceptions/
├── BaseAppException.php                                           ← classe abstraite de base
├── Vehicle/
│   ├── VehicleCreationException.php
│   ├── VehicleUpdateException.php
│   ├── VehicleNotFoundException.php
│   ├── VehicleListException.php
│   └── VehicleFiscalCharacteristicsValidationException.php
├── Company/
│   ├── CompanyCreationException.php
│   └── CompanyNotFoundException.php
├── Driver/
│   ├── DriverNotFoundException.php
│   └── DriverReplacementException.php
├── Assignment/
│   ├── AssignmentConflictException.php
│   ├── AssignmentListException.php
│   └── AssignmentBatchException.php
├── Unavailability/
│   └── UnavailabilityOverlapException.php
├── Declaration/
│   ├── DeclarationCalculationException.php
│   ├── DeclarationPdfGenerationException.php
│   └── DeclarationStatusTransitionException.php
└── Fiscal/
    ├── FiscalRulePipelineException.php
    └── FiscalRuleNotFoundException.php
```

---

## Checklist gestion d'erreur — avant de marquer une fonctionnalité comme « terminée »

- [ ] Chaque requête BDD complexe dans un repository est dans un `try/catch` qui lève une exception typée Floty.
- [ ] Les exceptions héritent de `BaseAppException` et portent un message technique (anglais, pour les logs) **et** un message utilisateur (français, pour l'affichage).
- [ ] Les exceptions sont organisées dans `app/Exceptions/{Domaine}/` selon le domaine métier (jamais par espace).
- [ ] Le controller catch les exceptions et **log dans le canal thématique adapté** avant de répondre.
- [ ] Les mutations (POST/PUT/DELETE) répondent par `back()->withInput()->with('toast-error', ...)`.
- [ ] Les lectures (GET) répondent par redirect vers une page parent avec `with('toast-error', ...)`.
- [ ] Les erreurs partielles (section qui échoue dans une page, le reste OK) passent par une **prop typée** dédiée (`xxxError: string | null`).
- [ ] Les FormRequest portent des messages en français et des `attributes` français pour les violations de règles.
- [ ] Les pages d'erreur 404, 500, 503 sont rendues via Inertia avec le layout cohérent.
- [ ] Le 419 CSRF est intercepté côté handler Laravel et redirige avec `toast-warning`.
- [ ] Les logs ne contiennent **jamais** de données sensibles (filtrage `$request->safe()->except([...])`).
- [ ] L'utilisateur ne voit **jamais** de stack trace, nom de classe, requête SQL, ni message technique anglais.

---

## Cohérence avec les autres règles

- **Architecture en couches** (Repository → Service → Action → Controller) avec exemples Floty : voir `architecture-solid.md`.
- **Conventions de nommage** (exceptions, FormRequest, factory methods) : voir `conventions-nommage.md`.
- **Structure des fichiers** (emplacement des Exceptions, FormRequests par espace) : voir `structure-fichiers.md`.
- **Bundling Vite** (entry, code splitting, anti-patterns skeleton) : voir `assets-vite.md`.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 2.0 | 24/04/2026 | Micha MEGRET | **Refonte complète** pour stack Floty (Laravel 13 + Inertia v3 + Vue 3 + TypeScript 6 + PHP 8.5). Suppression de Livewire (`addError`, hook `livewire:init`, `dispatch('toast')`). Ajout du volet Inertia/Vue : flash messages via shared props, `useForm` avec erreurs validation, ToastContainer global, pages d'erreur Inertia (404/500/503), 419 intercepté côté handler Laravel et `bootstrap/app.php`. Exemples métier Floty (vehicle, attribution, declaration, validation invariants WLTP/NEDC/PA, conflit attribution). Canaux de log thématiques Floty avec rétentions adaptées (déclarations 365j, fiscal 90j, etc.). Pattern erreur partielle via prop typée `xxxError: string | null`. Checklist mise à jour. Tout en français avec accents. |
| 1.0 | mars 2026 | Micha MEGRET | Version initiale, contexte ancien projet Livewire + Alpine + Blade. |
