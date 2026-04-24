# Architecture en couches — Vue → Inertia → Controller → Action → Service → Repository

> **Stack référence** : Laravel 13, Inertia v3, Vue 3.5, TypeScript 6, Spatie Laravel Data 4, PHP 8.5, MySQL 8.
> **Niveau d'exigence** : senior +, code soumis à critique de pairs experts (Laravel, Vue, TypeScript). Aucune erreur de débutant tolérée.

---

## Principe fondamental

Toute logique métier et toute interaction avec la base de données suivent une architecture en couches strictes. Chaque couche a une **responsabilité unique** et un **périmètre d'action limité**. Cette architecture s'applique à l'**ensemble** de l'application — il n'y a pas de raccourci toléré « parce que c'est petit ».

L'objectif est triple :

1. **Segmentation** — chaque fichier reste court (< 200 lignes en règle générale, < 400 en exception justifiée).
2. **Testabilité** — chaque couche se teste indépendamment, avec ses dépendances mockables via leurs interfaces.
3. **Évolutivité** — le passage de V1 à V1.2 (facturation), V2 (analytics, alertes), V3 (IA) se fait par ajout/extension, pas par réécriture.

> **Segmentation = maintenabilité = évolutivité.**

---

## Vue d'ensemble — la chaîne complète

Floty utilise **Inertia v3** comme pont entre Laravel et Vue 3. Inertia n'est pas une API REST : c'est un mécanisme qui sérialise la réponse PHP en JSON pour que Vue affiche la page comme s'il s'agissait d'une SPA, tout en conservant le routing Laravel natif.

La chaîne complète des couches, en **descente** (de l'utilisateur vers la base) puis en **remontée** (de la base vers l'utilisateur), est la suivante :

```
┌─────────────────────────────────────────────────────────────────────┐
│  COUCHE PRÉSENTATION (front, navigateur)                            │
│  ─────────────────────────────────────────                          │
│   Composant Vue (.vue)                                              │
│        │                                                            │
│        │ event utilisateur (click, submit, change, etc.)            │
│        ▼                                                            │
│   useForm / router (Inertia côté Vue)                               │
└────────┼────────────────────────────────────────────────────────────┘
         │ requête HTTP (XHR Inertia)
         ▼
┌─────────────────────────────────────────────────────────────────────┐
│  COUCHE PRÉSENTATION (back, Laravel)                                │
│  ───────────────────────────────────                                │
│   FormRequest (validation)                                          │
│        │                                                            │
│        ▼                                                            │
│   Controller Inertia                                                │
│        │                                                            │
│        │ délégation (jamais de logique métier ici)                  │
│        ▼                                                            │
└────────┼────────────────────────────────────────────────────────────┘
         │
┌────────┼────────────────────────────────────────────────────────────┐
│  COUCHES MÉTIER                                                     │
│  ──────────────                                                     │
│   Action (orchestrateur, transaction si besoin)                     │
│        │                                                            │
│        ├─────► Service (logique métier)                             │
│        │           │                                                │
│        │           ▼                                                │
│        │       Repository (BDD)                                     │
│        │           │                                                │
│        ▼           ▼                                                │
│       Service   ─► Repository                                       │
└────────┼─────────────────────────────────────────────────────────────┘
         │
         ▼
   ┌───────────┐
   │  MySQL 8  │
   └───────────┘
         │
         ▼ (lecture)
┌─────────────────────────────────────────────────────────────────────┐
│  REMONTÉE — TYPAGE FORT JUSQU'AU FRONT                              │
│  ──────────────────────────────────────                             │
│   Modèle Eloquent (interne)                                         │
│        │                                                            │
│        ▼                                                            │
│   Resource (Spatie Laravel Data DTO, immutable, typée)              │
│        │                                                            │
│        │ sérialisation JSON Inertia + génération auto type TS       │
│        ▼                                                            │
│   props Vue typées (TS strict)                                      │
└─────────────────────────────────────────────────────────────────────┘
```

**Trois principes structurants** à retenir avant de plonger dans le détail :

1. **Aucune couche ne saute une étape** sans justification documentée. Pas de `User::create()` dans un controller. Pas d'appel à un repository depuis un composant Vue. Pas de logique métier dans une Resource.
2. **Le DTO (Resource) est la frontière contractuelle** entre PHP et TypeScript. C'est l'endroit où le typage devient strict des deux côtés. La génération automatique des types TS (Spatie TypeScript Transformer) garantit qu'aucune divergence ne s'installe.
3. **Le 4-layer backend (Controller → Action → Service → Repository) est immuable**. La Resource est en réalité une 5ᵉ couche de remontée, qui ne se substitue à aucune des 4.

---

## 1. Composant Vue — couche présentation client

**Rôle** : afficher l'interface, capter les interactions utilisateur, déléguer les opérations à Inertia. Un composant Vue **n'a pas de logique métier**. Il a uniquement des préoccupations d'**affichage** et d'**interaction**.

### Responsabilités

- Recevoir des données via les **props typées** (issues d'une Resource).
- Émettre des événements ou déclencher des appels Inertia (`router.visit`, `router.post`, `useForm`).
- Gérer l'état purement local de l'UI (état d'ouverture d'un drawer, sélection multiple, etc.) via `ref` / `reactive` / composables.
- Composer des sous-composants pour la décomposition.

### Ne contient PAS

- D'appel direct à une API hors Inertia (sauf cas exceptionnel documenté).
- De manipulation de données métier (calcul fiscal, transformation d'attributions, agrégation).
- D'accès à `localStorage`, `sessionStorage` ou `document` sans passer par un composable dédié.
- De `fetch` brut ou `axios` standalone (Inertia v3 a un client XHR intégré).

### Exemple — page « Liste des véhicules » (Floty)

```vue
<!-- resources/js/Pages/Vehicles/Index.vue -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import type { VehicleListItemData } from '@/types/generated'
import VehicleCard from '@/Components/Vehicle/VehicleCard.vue'
import PageLayout from '@/Layouts/PageLayout.vue'

const props = defineProps<{
  vehicles: VehicleListItemData[]
  fiscalYear: number
}>()

const goToVehicle = (vehicleId: number): void => {
  router.visit(route('vehicles.show', { vehicle: vehicleId }))
}
</script>

<template>
  <PageLayout :title="`Flotte ${fiscalYear}`">
    <div class="grid grid-cols-3 gap-4">
      <VehicleCard
        v-for="vehicle in vehicles"
        :key="vehicle.id"
        :vehicle="vehicle"
        @click="goToVehicle(vehicle.id)"
      />
    </div>
  </PageLayout>
</template>
```

### Anti-patterns à éviter (repérés en revue senior)

| Anti-pattern | Correction |
|---|---|
| `axios.get('/api/vehicles')` dans un composant | Utiliser Inertia (`router.get`) ou un composable qui encapsule un appel JSON dédié si nécessaire. |
| `const total = computed(() => attributions.value.reduce(...))` recalculant un montant fiscal | Le calcul fiscal vient du backend, jamais reproduit côté Vue. |
| Mutation directe d'une prop : `props.vehicles.push(...)` | Émettre un événement ; les props sont **immutables** (TypeScript le force avec `Readonly<>`). |
| Mélange Options API et Composition API | Composition API stricte uniquement, avec `<script setup lang="ts">`. |
| `any` ou type implicite | Type explicite obligatoire ; tsconfig `strict: true`. |

---

## 2. Inertia côté Vue — `useForm`, `router`, `<Link>`

Inertia v3 fournit trois primitives de navigation/communication serveur :

| Primitive | Usage | Exemple Floty |
|---|---|---|
| `<Link>` | Navigation déclarative entre pages | Lien depuis la heatmap vers la fiche véhicule |
| `router.visit` / `.get` / `.post` / `.put` / `.delete` | Navigation/mutation impérative | Soumission programmée d'un wizard d'attribution |
| `useForm` | Formulaires avec gestion d'état + erreurs serveur | Création / édition de véhicule, saisie hebdomadaire |

`useForm` est **le standard** pour tout formulaire qui modifie des données serveur. Il porte l'état du formulaire, l'état de soumission (`processing`), les erreurs validation (alimentées automatiquement par les `FormRequest` Laravel), et la gestion fine du `reset` / `clearErrors`.

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import type { VehicleFormData } from '@/types/generated'

const props = defineProps<{ vehicle?: VehicleFormData }>()

const form = useForm({
  immatriculation: props.vehicle?.immatriculation ?? '',
  marque: props.vehicle?.marque ?? '',
  modele: props.vehicle?.modele ?? '',
  // ... autres champs typés
})

const submit = (): void => {
  form.post(route('vehicles.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  })
}
</script>
```

> Détails sur la navigation (préservation de scroll, partial reloads, deferred props, anti-patterns) : voir `inertia-navigation.md`.

---

## 3. Controller Inertia — point d'entrée HTTP

**Rôle** : recevoir la requête HTTP validée, déléguer à une Action, retourner soit une **réponse Inertia** (page), soit une **redirection** (mutation).

### Responsabilités

- Recevoir le `FormRequest` (validation déclarative côté Laravel).
- Appeler **une seule Action** (le cas standard) ou un service simple si le pragmatisme le justifie (cf. § Pragmatisme).
- Construire la **Resource** (Spatie Data) à partir du résultat retourné.
- Retourner via `Inertia::render()` (page) ou `redirect()->route()` (après mutation).
- Capturer les exceptions métier et les transformer en flash messages ou en erreurs Inertia.

### Ne contient PAS

- De logique métier (calcul, transformation, agrégation).
- De requête Eloquent directe (toujours via Repository ou Service).
- De manipulation manuelle des données pour les passer au front (toujours via Resource).
- De `try/catch` qui « avale » une exception sans la transformer en réponse propre.

### Convention de nommage

`{Entité}Controller` ou `{Entité}{Sous-rôle}Controller` quand le périmètre est restreint.

| Pattern | Exemple Floty |
|---|---|
| Controller CRUD complet | `VehicleController` (index, show, store, update, destroy) |
| Controller spécialisé | `VehicleFiscalCharacteristicsController` (historisation isolée) |
| Controller invocable | `GenerateDeclarationPdfController` (action unique, méthode `__invoke`) |

### Emplacement

`app/Http/Controllers/{Espace}/{Domaine}/` où `{Espace}` ∈ {`Web`, `User`}. La segmentation par espace est **en place dès V1** :

- `Web/` : controllers publics (accueil, mentions légales, login form).
- `User/` : controllers de l'espace connecté (vehicles, entreprises, declarations, planning, etc.).

Cette segmentation préempte volontairement V2 (ajout potentiel de `User/{Role}/` pour des rôles différenciés sans déplacement de fichiers).

> Cf. `architecture-solid.md` § Arborescence type pour la liste exhaustive des controllers Floty V1.

### Exemple — store d'un véhicule

```php
namespace App\Http\Controllers\User\Vehicle;

use App\Actions\User\Vehicle\CreateVehicleAction;
use App\Data\User\Vehicle\VehicleStoreData;
use App\Exceptions\Vehicle\VehicleCreationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Vehicle\StoreVehicleRequest;
use Illuminate\Http\RedirectResponse;

final class VehicleController extends Controller
{
    public function store(
        StoreVehicleRequest $request,
        CreateVehicleAction $action,
    ): RedirectResponse {
        try {
            $vehicle = $action->execute(VehicleStoreData::from($request->validated()));
        } catch (VehicleCreationException $e) {
            return back()->with('toast-error', $e->getUserMessage());
        }

        return redirect()
            ->route('user.vehicles.show', ['vehicle' => $vehicle->id])
            ->with('toast-success', 'Véhicule enregistré.');
    }
}
```

### Exemple — index avec Resource pour Vue

```php
public function index(VehicleListReadRepository $repository): Response
{
    $vehicles = $repository->listActive();

    return Inertia::render('User/Vehicles/Index/Index', [
        'vehicles' => VehicleListItemData::collect($vehicles),
        'fiscalYear' => current_fiscal_year(),
    ]);
}
```

Ici, `VehicleListItemData::collect($vehicles)` transforme la collection Eloquent en collection de DTO immutables typés. C'est cette structure qui est sérialisée par Inertia et qui pilote la génération du type TS `VehicleListItemData[]` consommé par `Pages/Vehicles/Index.vue`.

---

## 4. Action — orchestrateur

**Rôle** : orchestrer une opération métier complète. Une Action = une intention utilisateur cohérente (créer un véhicule, attribuer un planning, générer un PDF de déclaration).

### Responsabilités

- Coordonner les appels à un ou plusieurs services dans le bon ordre.
- Gérer les transactions BDD (`DB::transaction`) lorsque plusieurs opérations doivent être atomiques.
- Lever des exceptions métier typées en cas d'échec.
- Garantir les invariants transverses (idempotence, vérifications de pré-conditions globales).

### Ne contient PAS

- De logique métier elle-même (déléguée aux services).
- De requête Eloquent directe (déléguée aux repositories via les services).
- D'effets de bord cachés (toute notification, log, événement doit être explicite).

### Convention de nommage

`{Verbe}{Entité}Action` — verbe à l'infinitif anglais, entité au singulier.

| Pattern | Exemple Floty |
|---|---|
| Création | `CreateVehicleAction`, `CreateCompanyAction` |
| Mise à jour | `UpdateVehicleFiscalCharacteristicsAction` |
| Suppression | `SoftDeleteVehicleAction`, `HardDeleteVehicleAction` |
| Opération métier non-CRUD | `AssignVehicleToCompanyAction`, `GenerateDeclarationPdfAction`, `InvalidateDeclarationAction` |

### Emplacement

`app/Actions/{Espace}/{Domaine}/` où `{Espace}` ∈ {`Web`, `User`}. Cohérent avec la segmentation des controllers.

### Exemple Floty — création d'un véhicule avec historisation initiale

```php
namespace App\Actions\User\Vehicle;

use App\Data\User\Vehicle\VehicleStoreData;
use App\Models\Vehicle;
use App\Services\User\Vehicle\VehicleCreationService;
use App\Services\User\Vehicle\VehicleFiscalCharacteristicsService;
use Illuminate\Support\Facades\DB;

final readonly class CreateVehicleAction
{
    public function __construct(
        private VehicleCreationService $vehicleCreationService,
        private VehicleFiscalCharacteristicsService $fiscalCharacteristicsService,
    ) {}

    public function execute(VehicleStoreData $data): Vehicle
    {
        return DB::transaction(function () use ($data): Vehicle {
            $vehicle = $this->vehicleCreationService->create($data);

            $this->fiscalCharacteristicsService->createInitialVersion(
                vehicle: $vehicle,
                data: $data->fiscalCharacteristics,
            );

            return $vehicle;
        });
    }
}
```

L'usage de `final readonly class` (PHP 8.4+, en pratique PHP 8.5 sur Floty) est **obligatoire** pour toutes les Actions : elles n'ont pas d'état mutable, leur instance est résolue à chaque requête par le container Laravel.

### Exemple Floty — génération d'un PDF de déclaration fiscale (orchestration plus large)

```php
namespace App\Actions\User\Declaration;

use App\Models\Declaration;
use App\Models\DeclarationPdf;
use App\Services\User\Declaration\DeclarationCalculationService;
use App\Services\User\Declaration\DeclarationSnapshotService;
use App\Services\Shared\Pdf\DeclarationPdfRenderer;
use App\Services\Shared\Storage\DeclarationPdfStorage;
use Illuminate\Support\Facades\DB;

final readonly class GenerateDeclarationPdfAction
{
    public function __construct(
        private DeclarationCalculationService $calculationService,
        private DeclarationSnapshotService $snapshotService,
        private DeclarationPdfRenderer $pdfRenderer,
        private DeclarationPdfStorage $pdfStorage,
    ) {}

    public function execute(Declaration $declaration, int $userId): DeclarationPdf
    {
        return DB::transaction(function () use ($declaration, $userId): DeclarationPdf {
            $calculation = $this->calculationService->calculate($declaration);
            $snapshot = $this->snapshotService->build($declaration, $calculation);
            $pdfBinary = $this->pdfRenderer->render($snapshot);
            $storedPath = $this->pdfStorage->store($declaration, $pdfBinary);

            return DeclarationPdf::create([
                'declaration_id' => $declaration->id,
                'pdf_path' => $storedPath,
                'snapshot_json' => $snapshot,
                'pdf_sha256' => hash('sha256', $pdfBinary),
                'snapshot_sha256' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
                'generated_by' => $userId,
                'version_number' => $declaration->pdfs()->count() + 1,
            ]);
        });
    }
}
```

L'Action ne calcule rien elle-même — elle orchestre cinq services et la persistance finale dans une transaction atomique.

> **Note sur l'exemple ci-dessus** : le `DeclarationPdf::create([...])` final à l'intérieur de l'Action peut surprendre — l'Action a-t-elle le droit d'écrire en BDD directement ? Lecture honnête : non, par principe. Mais c'est ici un cas justifié et documenté : la persistance d'une ligne `DeclarationPdf` est une **opération technique de transcription** des résultats déjà calculés (snapshot, hash, chemin filesystem) qui n'a aucune logique métier propre. Créer un `DeclarationPdfWriteRepository` ou un `DeclarationPdfPersistenceService` ne ferait que déplacer cette ligne sans rien encapsuler — ce serait du sur-engineering selon les règles de pragmatisme. **À l'inverse, si la persistance impliquait une logique non triviale** (ex: numérotation séquentielle calculée par contention, vérification d'unicité avec retry), elle remonterait dans un Service ou un Repository. La règle se ramène au pragmatisme : créer une couche n'a de sens que si elle encapsule de la complexité.

### Qui orchestre quoi ? — Action vs Service face aux Repositories

Question récurrente en architecture en couches : **dans une chaîne `Action → Service → Repository`, qui appelle qui** ?

**Approche A — Service appelle ses Repositories (par défaut)**
- L'Action orchestre des Services.
- Chaque Service appelle ses propres Repositories.
- L'Action voit un graphe de Services, pas de Repositories.

```
Action.execute()
  └─► ServiceA.method()
        └─► RepoA.read()
        └─► RepoA.write()
  └─► ServiceB.method()
        └─► RepoB.write()
```

**Approche B — Action appelle directement les Repositories (exception documentée)**
- L'Action orchestre Services **et** Repositories.
- Les Services deviennent des **calculateurs purs** (input → output, sans persistance).
- L'Action décide quoi faire des résultats (persister, journaliser, notifier).

```
Action.execute()
  └─► ServiceCalc.compute(input) → result (data only)
  └─► RepoX.write(result)
  └─► NotificationService.send(result)
```

#### Règle par défaut : Approche A

**L'Approche A est le standard pour Floty.** Raisons :

1. **Encapsulation forte** : un Service est responsable d'un domaine de bout en bout (calcul + lecture + écriture). On peut comprendre le Service sans comprendre l'Action.
2. **Cohésion** : les opérations BDD liées à une logique métier vivent au même endroit que cette logique métier.
3. **Testabilité naturelle** : le Service se teste en mockant ses Repositories ; l'Action se teste en mockant ses Services.
4. **Évite la prolifération de paramètres** : pas besoin de faire transiter de gros payloads entre Action et Repository.

#### Quand basculer en Approche B

L'Approche B fait sens dans **trois cas précis et reconnaissables** :

**Cas 1 — Calcul réutilisable, persistance contextuelle**

Un Service produit un résultat qui peut être consommé de plusieurs façons : persisté en base, sérialisé en JSON pour un PDF, exporté en CSV, envoyé par email. Le Service ne sait pas ce qui en sera fait. **L'Action décide.**

```php
// Approche B justifiée : DeclarationCalculationService est un calculateur pur
final readonly class GenerateDeclarationPdfAction
{
    public function execute(Declaration $declaration): DeclarationPdf
    {
        // Service = calcul pur, retourne une structure de données
        $calculation = $this->calculationService->calculate($declaration);

        // L'Action décide : ici on persiste un PDF et un snapshot
        $snapshot = $this->snapshotService->build($declaration, $calculation);
        $pdfBinary = $this->pdfRenderer->render($snapshot);

        // L'Action écrit via le repository (cohérent : c'est elle qui orchestre)
        return $this->declarationPdfWriteRepository->persist(
            declaration: $declaration,
            pdfBinary: $pdfBinary,
            snapshot: $snapshot,
        );
    }
}
```

Le même `DeclarationCalculationService::calculate()` peut servir au mode « simulation temps réel » (compteur LCD) sans persistance, au mode « génération PDF » avec persistance, et au mode « export CSV » via une autre Action — sans dupliquer la logique de calcul.

**Cas 2 — Composition transactionnelle de plusieurs domaines**

Quand l'Action coordonne plusieurs domaines indépendants qui doivent être atomiques, et qu'aucun Service métier n'a de raison d'avoir connaissance des autres.

```php
// Cas Floty : invalider les déclarations dépendantes lors d'un changement de caractéristiques fiscales
final readonly class UpdateVehicleFiscalCharacteristicsAction
{
    public function execute(Vehicle $vehicle, VehicleFiscalCharacteristicsData $data): void
    {
        DB::transaction(function () use ($vehicle, $data): void {
            $this->fiscalCharacteristicsService->createNewEffectiveVersion($vehicle, $data);

            // L'Action coordonne, le service de détection est appelé puis le repo
            $impactedDeclarations = $this->invalidationDetector->findImpacted($vehicle);
            $this->declarationWriteRepository->markAsInvalidated(
                declarationIds: $impactedDeclarations->pluck('id')->all(),
                reason: InvalidationReason::VehicleCharacteristicsChanged,
            );
        });
    }
}
```

Ici, demander à `VehicleFiscalCharacteristicsService` de connaître l'existence de `Declaration` casserait l'isolation des domaines. C'est l'Action qui fait le pont.

**Cas 3 — Action transverse infrastructure**

Action qui orchestre des opérations techniques (cache, file d'attente, événement) en plus de l'écriture métier. Le Service métier reste pur, l'Action gère l'infrastructure.

#### Règle de décision

| Situation | Approche |
|---|---|
| Une seule logique métier, persistance directement liée | **A** — Service appelle son Repository |
| Calcul réutilisable, persistance contextuelle (PDF, CSV, simulation…) | **B** — Service calcule, Action persiste |
| Coordination cross-domaines transactionnelle | **B** — Action coordonne plusieurs services + repositories |
| CRUD simple sans logique | Pragmatisme — service appelle Eloquent direct, voire controller appelle service direct |

> **Heuristique senior** : si tu te demandes « ce Service connaît-il trop de choses qui ne le concernent pas directement ? », tu es probablement dans un cas Approche B mal appliquée comme A. Réintroduis l'orchestration dans l'Action.

---

## 5. Service — logique métier

**Rôle** : porter la logique métier pure. Transformer, valider, calculer, appliquer les règles business.

### Responsabilités

- Implémenter une règle ou un calcul métier identifiable.
- Lire/écrire via les repositories ou directement Eloquent (cf. § Pragmatisme).
- Composer d'autres services si la logique nécessite plusieurs sous-opérations.
- Lever des exceptions métier typées si une règle est violée.

### Ne contient PAS

- D'appel à `Inertia::render` (c'est le rôle du controller).
- De gestion de transaction (c'est le rôle de l'Action).
- De logique de présentation (formatage destiné à l'UI).

### Convention de nommage

`{Entité}{Responsabilité}Service`.

| Pattern | Exemple Floty |
|---|---|
| Création | `VehicleCreationService` |
| Mise à jour | `VehicleFiscalCharacteristicsService` (création de version effective vs correction de saisie) |
| Calcul | `DeclarationCalculationService`, `LcdCumulCalculationService` |
| Snapshot | `DeclarationSnapshotService` |
| Rendu | `DeclarationPdfRenderer` |

### Emplacement

`app/Services/{Espace}/{Domaine}/` ou `app/Services/Shared/{Domaine}/` (pour les services transverses utilisés par plusieurs espaces, ex: `Shared/Fiscal/`, `Shared/Pdf/`, `Shared/Storage/`, `Shared/Cache/`).

### Exemple Floty — service d'historisation des caractéristiques fiscales

L'exemple ci-dessous montre une vraie logique métier : **valider** les invariants cross-champs imposés par le métier fiscal (cohérence méthode d'homologation ↔ champ CO₂ correspondant, source d'énergie hybride ↔ moteur thermique sous-jacent), **calculer** la date de fermeture de la version courante, **lever** des exceptions typées si une règle est violée, puis déléguer la persistance au Repository.

```php
namespace App\Services\User\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Data\User\Vehicle\VehicleFiscalCharacteristicsData;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\EnergySource;
use App\Exceptions\Vehicle\VehicleFiscalCharacteristicsValidationException;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

final readonly class VehicleFiscalCharacteristicsService
{
    public function __construct(
        private VehicleFiscalCharacteristicsWriteRepositoryInterface $writeRepository,
    ) {}

    /**
     * Crée la version initiale des caractéristiques fiscales à l'acquisition du véhicule.
     *
     * Logique métier :
     *   1. Valider la cohérence interne du jeu de données (méthode hom ↔ co2_*, source hybride ↔ moteur sous-jacent).
     *   2. Calculer la `change_reason` (toujours CreationInitiale ici).
     *   3. Persister via le Repository.
     */
    public function createInitialVersion(
        Vehicle $vehicle,
        VehicleFiscalCharacteristicsData $data,
    ): VehicleFiscalCharacteristics {
        $this->assertCrossFieldInvariants($data);

        return $this->writeRepository->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => $vehicle->date_acquisition,
            'effective_to' => null,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
            ...$data->toArray(),
        ]);
    }

    /**
     * Ferme la version courante et crée une nouvelle version effective à la date donnée.
     *
     * Cette méthode contient une transaction interne par exception au principe général :
     * la fermeture-puis-création est une opération atomique unique du point de vue métier
     * (« historiser une modification »), pas une orchestration cross-domaines. La transaction
     * locale au service est ici l'option la plus cohérente — cf. § Transactions et pragmatisme.
     */
    public function createNewEffectiveVersion(
        Vehicle $vehicle,
        VehicleFiscalCharacteristicsData $newData,
        Carbon $effectiveFrom,
        ?string $changeNote = null,
    ): VehicleFiscalCharacteristics {
        $this->assertCrossFieldInvariants($newData);

        return DB::transaction(function () use ($vehicle, $newData, $effectiveFrom, $changeNote): VehicleFiscalCharacteristics {
            $current = $this->writeRepository->findCurrentForVehicle($vehicle->id);

            if ($effectiveFrom->lessThanOrEqualTo(Carbon::parse($current->effective_from))) {
                throw VehicleFiscalCharacteristicsValidationException::effectiveFromBeforeCurrent(
                    $vehicle->id,
                    $effectiveFrom,
                    Carbon::parse($current->effective_from),
                );
            }

            $this->writeRepository->closeVersion(
                version: $current,
                effectiveTo: $effectiveFrom->copy()->subDay(),
            );

            return $this->writeRepository->create([
                'vehicle_id' => $vehicle->id,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'change_reason' => FiscalCharacteristicsChangeReason::EffectiveChange,
                'change_note' => $changeNote,
                ...$newData->toArray(),
            ]);
        });
    }

    /**
     * Vérifie les règles métier transverses imposées par le catalogue fiscal Floty.
     */
    private function assertCrossFieldInvariants(VehicleFiscalCharacteristicsData $data): void
    {
        match ($data->homologationMethod) {
            HomologationMethod::Wltp => $data->co2Wltp ?? throw VehicleFiscalCharacteristicsValidationException::missingCo2Wltp(),
            HomologationMethod::Nedc => $data->co2Nedc ?? throw VehicleFiscalCharacteristicsValidationException::missingCo2Nedc(),
            HomologationMethod::Pa => $data->taxableHorsepower ?? throw VehicleFiscalCharacteristicsValidationException::missingPuissanceAdmin(),
        };

        $isHybride = in_array(
            $data->energySource,
            [EnergySource::PluginHybrid, EnergySource::NonPluginHybrid, EnergySource::ElectricHydrogen],
            strict: true,
        );

        if ($isHybride && $data->underlyingCombustionEngineType === null) {
            throw VehicleFiscalCharacteristicsValidationException::hybrideRequiresMoteurThermique($data->energySource);
        }
    }
}
```

**Ce que cet exemple démontre** :

- Une vraie logique métier (validation d'invariants fiscaux, calcul de `effective_to`, levée d'exceptions typées) — pas un simple proxy vers Eloquent.
- L'usage de `match` PHP (vérification exhaustive des cas d'enum à la compilation).
- L'exception transactionnelle interne au service est explicitement justifiée en commentaire (cf. § Transactions et pragmatisme).
- Le Repository est invoqué à travers son interface — pas d'`Eloquent` directement.
- Les exceptions métier portent du contexte typé pour faciliter le diagnostic (`missingCo2Wltp`, `effectiveFromBeforeCurrent`, etc.).

---

## 6. Repository — interaction BDD

**Rôle** : seul point d'interaction avec la base de données pour les **requêtes complexes**. Encapsule les requêtes Eloquent qui demandent du filtrage avancé, des sous-requêtes, des jointures, ou de la réutilisation.

### Quand un Repository est justifié

- La requête implique du filtrage complexe, des scopes combinés, des sous-requêtes ou des jointures.
- La même requête complexe est réutilisée à plusieurs endroits.
- La logique de requête risque d'évoluer indépendamment de la logique métier.
- On veut isoler des requêtes spécifiques pour les tester unitairement.

### Quand un Repository est du sur-engineering (cf. § Pragmatisme)

- Un simple `Vehicle::find($id)`.
- Un `Company::create($data)`.
- Une lecture triviale `Driver::where('entreprise_id', $id)->get()`.

Dans ces cas, **le service appelle Eloquent directement**.

### Convention de nommage

`{Entité}{Responsabilité}Repository` — la responsabilité est explicite : `Read`, `Write`, `Search`, etc.

### Emplacement

`app/Repositories/{Espace}/{Entité}/` pour l'implémentation, `app/Contracts/Repositories/{Espace}/{Entité}/` pour l'interface (miroir strict).

### Exemple Floty — repository de lecture pour le compteur LCD par couple

```php
namespace App\Repositories\User\Assignment;

use App\Contracts\Repositories\User\Assignment\LcdCumulReadRepositoryInterface;
use App\Exceptions\Assignment\AssignmentListException;
use App\Models\Assignment;
use App\Models\Unavailability;
use Illuminate\Support\Facades\DB;

final readonly class LcdCumulReadRepository implements LcdCumulReadRepositoryInterface
{
    public function countDaysForCoupleInYear(int $vehicleId, int $companyId, int $year): int
    {
        try {
            $assignedDays = Assignment::query()
                ->where('vehicle_id', $vehicleId)
                ->where('entreprise_id', $companyId)
                ->whereYear('date', $year)
                ->whereNull('deleted_at')
                ->count();

            $fourriereDays = Unavailability::query()
                ->where('vehicle_id', $vehicleId)
                ->where('has_fiscal_impact', true)
                ->whereYear('start_date', '<=', $year)
                ->where(fn ($q) => $q->whereYear('end_date', '>=', $year)->orWhereNull('end_date'))
                ->whereNull('deleted_at')
                ->get()
                ->sum(fn (Unavailability $i) => $i->daysOverlappingYear($year));

            return max(0, $assignedDays - $fourriereDays);
        } catch (\Throwable $e) {
            throw AssignmentListException::lcdCumulFailed($vehicleId, $companyId, $year, $e);
        }
    }
}
```

---

## 7. Resource — DTO de remontée Spatie Laravel Data

**Rôle** : matérialiser le contrat de données entre PHP et Vue/TypeScript. Toute donnée envoyée au front passe par une Resource. Le typage est strict des deux côtés.

C'est la **5ᵉ couche** de l'architecture, située sur le chemin de remontée (du backend vers le navigateur). Elle ne se substitue à aucune autre couche.

### Pourquoi une couche dédiée

| Sans Resource | Avec Resource |
|---|---|
| `Inertia::render('Page', ['vehicles' => Vehicle::all()])` envoie tous les champs Eloquent | Choix explicite des champs exposés, immuabilité, structure stable |
| Type TS écrit à la main, divergence garantie au fil du temps | Type TS généré automatiquement depuis la classe PHP |
| Pas de validation au passage de la frontière | Coercion typée (`int`, `string`, `Carbon`, `array<X>`) |
| Le composant Vue dépend du modèle Eloquent (couplage fort) | Le composant Vue dépend du DTO (couplage faible, contrat stable) |

### Convention

Toutes les Resources **héritent de `Spatie\LaravelData\Data`** (ou `Spatie\LaravelData\Dto` pour les DTO d'entrée).

Toutes sont **`final readonly`** et annotées `#[TypeScript]` pour la génération auto du type.

### Emplacement

`app/Data/{Espace}/{Domaine}/` (cohérent avec la segmentation des controllers et actions).

### Convention de nommage

`{Entité}{UsageOptionnel}Data` :

- `VehicleData` — représentation complète d'un véhicule
- `VehicleListItemData` — version allégée pour l'affichage en liste (sans toutes les caractéristiques fiscales détaillées)
- `VehicleFormData` — version pour formulaire édition (subset modifiable)
- `VehicleFiscalCharacteristicsData` — DTO d'entrée pour la création/modification

### Exemple Floty — DTO d'un véhicule en liste

```php
namespace App\Data\User\Vehicle;

use App\Enums\Vehicle\VehicleUserType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class VehicleListItemData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $immatriculation,
        public readonly string $marque,
        public readonly string $modele,
        public readonly VehicleUserType $vehicleUserType,
        public readonly ?string $photoUrl,
        public readonly bool $isActive,
    ) {}
}
```

Type TypeScript généré automatiquement (commande `php artisan typescript:transform`) :

```ts
// resources/js/types/generated.d.ts
export type VehicleListItemData = {
  id: number
  immatriculation: string
  marque: string
  modele: string
  vehicleUserType: VehicleUserType
  photoUrl: string | null
  isActive: boolean
}
```

Le composant Vue consomme ce type sans aucune duplication manuelle :

```vue
<script setup lang="ts">
import type { VehicleListItemData } from '@/types/generated'

defineProps<{ vehicles: VehicleListItemData[] }>()
</script>
```

### Composition de Data

Une Resource peut **embarquer** d'autres Resources, ce qui modélise les structures imbriquées sans perte de typage :

```php
#[TypeScript]
final class DeclarationData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly CompanyData $company,
        public readonly int $fiscalYear,
        public readonly DeclarationStatus $status,
        public readonly ?int $totalTaxeCo2,
        public readonly ?int $totalTaxePolluants,
        public readonly bool $isInvalidated,
        /** @var DeclarationPdfData[] */
        public readonly array $pdfs,
    ) {}
}
```

Le PHPDoc `@var DeclarationPdfData[]` est lu par Spatie Data **et** par TypeScript Transformer pour générer correctement le type TS `DeclarationPdfData[]`.

### Règles strictes Resources

| Règle | Pourquoi |
|---|---|
| `final readonly class extends Data` | Immutabilité, pas d'héritage dérivé. |
| Toutes les propriétés `public readonly` typées | Garantie d'immutabilité, type contract clair. |
| Annotation `#[TypeScript]` obligatoire si exposée au front | Sinon le type n'est pas généré → divergence garantie. |
| Pas de logique métier dans la Resource | C'est un transport de données, pas un service. Méthodes statiques `from`, `collect` (Spatie) tolérées. |
| Pas d'accès BDD dans la Resource | Le Resource reçoit déjà les données chargées (eager loading préalable côté repository/service). |
| Conversion explicite Eloquent → Resource via `Data::from($model)` | Préfère cette syntaxe à la construction manuelle. |

### Anti-pattern fréquent

Inertia tolère qu'on lui passe directement un modèle Eloquent : `Inertia::render('Page', ['vehicle' => $vehicle])`. Eloquent expose alors tous les champs visibles. **C'est interdit en V1 Floty** : passer toujours par une Resource explicite. La discipline ici protège contre les fuites de données et garantit la stabilité du contrat front/back.

### Conformité de la pratique aux standards seniors

Cette approche **DTO Spatie Data + génération automatique des types TypeScript** peut surprendre par rapport au réflexe « j'écris mon DTO en PHP, j'écris mon type TS à la main, en miroir ». Elle est pourtant **devenue un standard moderne** dans l'écosystème Laravel + Inertia + Vue/React, pour des raisons techniques solides :

1. **Adopté par l'équipe Inertia officielle** : la documentation Inertia v3 référence explicitement `spatie/laravel-data` comme l'approche recommandée pour les DTO de page.
2. **Adopté par l'équipe Spatie** : les DTO `Spatie\LaravelData\Data` + `spatie/typescript-transformer` sont des packages flagship Spatie, utilisés en production sur leurs propres produits (Mailcoach, Flare, etc.).
3. **Adopté par le starter kit officiel Laravel Vue** depuis 2025 : la stack par défaut de `laravel new` (option Vue) inclut TypeScript et Spatie Data.
4. **Adopté par Laravel Cloud, Pulse, Reverb** : les produits officiels Laravel utilisent Spatie Data pour leurs DTO front/back.

**Pourquoi cette pratique paraît « inhabituelle »** : le réflexe « DTO + type TS écrits manuellement en miroir » date d'avant la maturité de l'écosystème (Spatie Data v3 stable depuis 2023, `spatie/typescript-transformer` v3 stable depuis fin 2025). Beaucoup de projets pré-2024 maintiennent encore l'ancien modèle par inertie. Sur un projet démarré en avril 2026 avec une revue senior+, **ce serait l'inverse qui interrogerait** : pourquoi maintenir manuellement deux représentations alors que l'auto-génération est mature et fiable ?

**Alternatives explicitement écartées** :

| Alternative | Pourquoi écartée |
|---|---|
| Laravel API Resources + types TS manuels | Doublon à maintenir, divergence garantie au fil du temps, pas de coercion typée à la frontière |
| `Inertia::render` avec modèles Eloquent | Fuite de données par défaut, couplage fort front/back, pas de type contract |
| DTO PHP custom (sans Spatie) + types TS auto | Écosystème de génération mature uniquement autour de Spatie ; refaire la roue n'apporte rien |
| Schémas JSON (OpenAPI / TypeSpec) | Pertinent pour APIs publiques, sur-engineered pour Inertia (couplage natif PHP↔Vue plus direct) |

**Coût d'adoption** :

- Une commande `php artisan typescript:transform` à intégrer dans le pre-commit hook ou le `npm run build`.
- Une dépendance composer (`spatie/laravel-data`) et une dépendance dev (`spatie/typescript-transformer`) — packages Spatie, fiabilité et maintenance reconnues.
- Une discipline : annoter les Data classes avec `#[TypeScript]`.

**Bénéfice mesurable** :

- 0 divergence type back/front, garantie par construction.
- Refactoring sécurisé : renommer un champ PHP propage l'erreur TS au build suivant.
- Code review allégé : on ne relit plus deux DTO en miroir.
- Onboarding facilité : un nouveau dev voit le DTO PHP et n'a aucun doute sur le contract.

**Verdict** : pratique senior reconnue, recommandée pour Floty.

---

## Interfaces (contrats) — Repositories

Chaque Repository implémente une interface. Les services dépendent de l'**interface**, jamais de l'implémentation concrète.

### Pourquoi

- **Testabilité** : on mocke l'interface, pas le code SQL.
- **Substituabilité** : on peut basculer d'une implémentation Eloquent à une implémentation cache, ou à une implémentation read-replica, sans toucher au service.
- **Contrats explicites** : l'interface documente ce que le repository garantit (signature, exceptions levées).

### Emplacement

`app/Contracts/Repositories/{Entité}/`.

### Exemple

```php
namespace App\Contracts\Repositories\User\Assignment;

interface LcdCumulReadRepositoryInterface
{
    /**
     * @throws \App\Exceptions\Assignment\AssignmentListException
     */
    public function countDaysForCoupleInYear(int $vehicleId, int $companyId, int $year): int;
}
```

### Binding dans le ServiceProvider

```php
namespace App\Providers;

use App\Contracts\Repositories\User\Assignment\LcdCumulReadRepositoryInterface;
use App\Repositories\User\Assignment\LcdCumulReadRepository;
use Illuminate\Support\ServiceProvider;

final class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        LcdCumulReadRepositoryInterface::class => LcdCumulReadRepository::class,
    ];
}
```

Le provider est enregistré dans `bootstrap/providers.php`.

### Pour les Services et Actions ?

**En règle générale, pas d'interface pour les services et actions.** Ils dépendent uniquement d'autres services / repositories (déjà mockables via leurs interfaces).

**Exceptions justifiées** :
- Service avec deux implémentations concurrentes (ex: `PdfRendererInterface` avec implémentations `DomPdfRenderer` et `BrowsershotRenderer` quand on basculera sur VPS).
- Action utilisée par plusieurs domaines indépendants devant pouvoir être mockée à grande échelle.

Dans tous les autres cas, **introduire une interface qui n'a qu'une implémentation est du sur-engineering**.

---

## Transactions

Utiliser `DB::transaction()` dans les **Actions** dès que **plusieurs opérations d'écriture** doivent réussir ensemble ou échouer ensemble.

### Quand utiliser une transaction

| Situation Floty | Transaction ? |
|---|---|
| Créer un véhicule + créer la version initiale de ses caractéristiques fiscales | **Oui** (2 écritures liées) |
| Mettre à jour un champ unique (renommer un véhicule) | Non |
| Créer une attribution (1 ligne) | Non |
| Bulk insert de 100 attributions (saisie hebdomadaire) | **Oui** (atomicité du batch) |
| Générer un PDF de déclaration : calcul + snapshot + persist + invalidation éventuelle | **Oui** (cohérence du livrable fiscal) |
| Modifier les caractéristiques d'un véhicule + invalider les déclarations dépendantes | **Oui** (cohérence transverse) |

### Pattern

La transaction est **toujours dans l'Action**, jamais dans le service ou le repository. L'Action est l'orchestrateur, c'est elle qui sait si l'opération est atomique.

```php
public function execute(VehicleData $data): Vehicle
{
    return DB::transaction(function () use ($data): Vehicle {
        $vehicle = $this->vehicleCreationService->create($data);
        $this->fiscalCharacteristicsService->createInitialVersion($vehicle, $data->fiscalCharacteristics);

        return $vehicle;
    });
}
```

### Spécificités MySQL

MySQL 8 (Hostinger) supporte les transactions sur InnoDB (moteur par défaut). Attention :

- **DDL non-transactionnel** : `CREATE TABLE`, `ALTER TABLE` provoquent un commit implicite. Toute migration doit être pensée hors transaction métier.
- **Niveau d'isolation par défaut** : `REPEATABLE READ`. Suffisant pour Floty mais à connaître pour comprendre certains comportements de lecture pendant une transaction.
- **Pas d'exclusion constraint native** (vs PostgreSQL) : les invariants comme « aucun chevauchement de périodes pour un véhicule » sont à gérer en applicatif (validation + verrou pessimiste si concurrent).

### Transactions et pragmatisme — corrélation critique

La règle « la transaction est dans l'Action » s'articule étroitement avec la règle « pas d'Action si pragmatisme ». **Le besoin transactionnel est un signal architectural** : si une opération nécessite plusieurs écritures atomiques, alors l'orchestration n'est plus triviale, et **la couche Action redevient nécessaire**.

> **Règle décisive** : le pragmatisme autorise à sauter l'Action **uniquement si l'opération est aussi transactionnellement triviale** (≤ 1 écriture). Dès qu'il faut plusieurs écritures atomiques, on remonte la couche Action.

| Cas | Action requise ? | Justification |
|---|---|---|
| Renommer un véhicule (1 UPDATE) | Non — controller appelle service direct | 1 écriture, pas de besoin transactionnel |
| Créer un véhicule + version initiale fiscale (2 INSERT atomiques) | **Oui** | 2 écritures liées, atomicité requise |
| Lire la liste des véhicules | Non — controller appelle repository direct | 0 écriture |
| Soft-delete un conducteur (1 UPDATE de `deleted_at`) | Non — controller appelle service direct | 1 écriture |
| Remplacer un conducteur sur N attributions futures (UPDATE batch) | **Oui** | UPDATE multi-lignes traité en transaction par cohérence |
| Bulk save d'une semaine de saisie tableur (50 INSERT/UPDATE) | **Oui** | Atomicité du batch, rollback complet en cas d'échec |

**Anti-pattern** : « j'avais sauté l'Action par pragmatisme, mais j'ai besoin d'une transaction, alors je la mets dans le Service ». Mauvaise réponse — c'est le signal qu'on aurait dû avoir l'Action depuis le début. Refactorer en introduisant l'Action plutôt que polluer le Service avec une responsabilité qui n'est pas la sienne.

**Exception unique tolérée** : un Service qui orchestre intrinsèquement plusieurs écritures **dans son propre périmètre métier** (ex: `VehicleFiscalCharacteristicsService::createNewEffectiveVersion` qui ferme la version courante puis crée la nouvelle — c'est une opération atomique unique du point de vue métier, pas une orchestration cross-domaines). Dans ce cas la transaction reste exceptionnellement dans le service, **et c'est documenté en commentaire de la méthode**.

---

## Accessors et attributs personnalisés — prudence

Les accessors Eloquent (`Attribute::make()`) sont une source fréquente de problèmes de performance et de bugs subtils. **Leur usage est strictement encadré.**

### Risques

1. **N+1 silencieux** — un accessor qui exécute une requête BDD (ex: `$this->attributions()->count()`) est invisible dans le code appelant. Quand il est appelé dans une boucle (ex: liste des 100 véhicules de la flotte), il génère 100 requêtes supplémentaires sans que le développeur s'en rende compte.

2. **Collision de noms** — si un accessor porte le même nom qu'une propriété ou une relation du modèle, il masque l'original. Cela peut casser des sous-requêtes optimisées qui s'attendent à accéder à la colonne ou à la relation directement.

3. **Couplage avec la couche présentation** — un accessor qui formate une donnée pour l'affichage (`getFormattedDateAttribute`) est de la logique de présentation dans le modèle. Cela n'a rien à faire dans une Resource Floty.

### Règles

| Autorisé | Interdit |
|---|---|
| Accessor qui formate/transforme des attributs déjà chargés (ex: `is_currently_active = effective_to === null`) | Accessor qui exécute une requête BDD (`$this->relation()->min()`, `count()`, etc.) |
| Accessor qui calcule à partir de propriétés en mémoire | Accessor qui charge une relation non-eager-loadée |
| Accessor qui retourne une enum ou un value object typé | Accessor qui formate pour l'UI (préférer la Resource) |

### Alternative : sous-requête via repository

Pour les valeurs calculées qui nécessitent une requête BDD (ex: nombre d'attributions sur l'année, taxe estimée totale), utiliser une **sous-requête corrélée** via `addSelect()` dans le repository :

```php
Vehicle::query()
    ->addSelect(['assignment_count_year' => Assignment::selectRaw('COUNT(*)')
        ->whereColumn('vehicle_id', 'vehicles.id')
        ->whereYear('date', $year)
    ])
    ->get();
```

Cette approche :

- Exécute la valeur calculée dans la même requête SQL (0 requête supplémentaire).
- Est explicite dans le code appelant (on voit le `addSelect` dans le repository).
- Ne risque pas de collision avec les propriétés du modèle.
- S'optimise facilement (index, cache, etc.).

Les repositories sont l'endroit idéal pour encapsuler ces sous-requêtes complexes — c'est précisément leur rôle dans l'architecture.

---

## Pragmatisme — quand simplifier

L'architecture en couches est un guide, pas un dogme. **Le pragmatisme est une valeur fondamentale** : chaque couche doit justifier son existence par la complexité qu'elle encapsule. Créer une couche qui ne fait que passer un appel sans ajouter de valeur est du sur-engineering.

### Le repository : réservé aux cas complexes

Le repository est la couche la plus souvent sur-utilisée. **Ne pas créer de repository** pour des opérations Eloquent triviales. Un repository qui ne fait que `Model::create($data)`, `Model::find($id)` ou `Model::query()->where(...)->get()` sans logique supplémentaire n'apporte aucune valeur — il ajoute un fichier, une interface, un binding dans le ServiceProvider, le tout pour zéro bénéfice.

**Créer un repository quand :**

- La requête implique du filtrage complexe, des scopes combinés, des sous-requêtes, ou des jointures.
- La même requête complexe est réutilisée à plusieurs endroits.
- La logique de requête risque d'évoluer indépendamment de la logique métier.
- On veut isoler des requêtes spécifiques pour les tester unitairement.

**Ne PAS créer de repository quand :**

- L'opération est un simple CRUD sur un seul modèle (`create`, `update`, `delete`, `find`).
- La requête est une lecture simple (`where` basique, `all`, `paginate`).
- Le repository ne ferait que « passer » l'appel au modèle Eloquent sans rien ajouter.

Dans ces cas, le service appelle directement Eloquent. C'est plus simple, plus lisible, et parfaitement acceptable.

### Quand sauter une couche

| Situation | Approche |
|---|---|
| CRUD trivial sur un seul modèle | Le service appelle Eloquent directement (pas de repository). |
| L'action ne ferait qu'appeler un seul service sans transaction | Le controller peut appeler le service directement (pas d'Action). |
| Page publique (mentions légales, accueil) sans logique | Controller invocable simple, pas de couches. |
| Opération de lecture pour rendu Inertia, sans transformation | Le controller appelle le repository directement (pas de service). |

### Règles de décision

1. **Une opération simple, pas de logique** → appel inline (Eloquent direct).
2. **Logique métier sans complexité d'orchestration** → Service direct (pas d'Action).
3. **Orchestration de plusieurs services / repos, ou transaction** → Action complète.
4. **Requête BDD avec logique de filtrage / recherche complexe** → Repository dédié.

### Seuil de refactoring

Si une méthode dépasse **30-40 lignes** (ou 60 en cas exceptionnel justifié), c'est le signal pour extraire vers la couche inférieure. Mieux vaut commencer simple et extraire quand le besoin se présente, plutôt que sur-architecturer dès la première opération.

### Principe directeur

> **Quand on hésite entre « ajouter une couche » et « garder simple » : garder simple.**

On peut toujours extraire vers une couche dédiée plus tard quand la complexité le justifie. L'inverse (supprimer une couche devenue inutile) est plus coûteux et arrive rarement.

---

## Autorisation et défense en profondeur

### Principe

L'authentification garantit que l'utilisateur est connecté (middleware `auth` natif Laravel). Mais en V1 Floty, **tous les utilisateurs ont les mêmes droits** (cf. ADR-0007 et ADR-0002). Il n'y a pas de séparation rôles / permissions.

**Cela ne dispense pas** de la défense en profondeur. Le besoin évolue (V2 introduira des rôles), et entre-temps certaines opérations méritent une vérification explicite : par exemple, un user ne peut pas modifier les caractéristiques fiscales d'un véhicule sorti de flotte sans confirmation, etc.

### Guard dans les Actions (défense en profondeur, V2-ready)

Toute Action qui reçoit un identifiant utilisateur ou qui modifie des données critiques **doit vérifier** ses préconditions :

```php
public function execute(Vehicle $vehicle, VehicleData $data, int $actorUserId): Vehicle
{
    if ($vehicle->date_exit !== null) {
        throw VehicleUpdateException::cannotUpexitDateedVehicle($vehicle->id);
    }

    // ... logique
}
```

Quand les rôles arriveront (V2), ces guards seront enrichis sans nécessiter de refonte des Actions.

### Policies pour les opérations sensibles

Même en V1 sans rôles, les Policies Laravel restent utiles pour documenter qui peut faire quoi. Elles vivent dans `app/Policies/` et sont appelées explicitement avant les opérations destructives :

```php
// Dans le controller
$this->authorize('hardDelete', $vehicle);
```

En V1 toutes les Policies retourneront `true` pour tout user authentifié, mais leur structure est en place pour V2.

### Résumé

| Couche | Vérification | V1 | V2 |
|---|---|---|---|
| Middleware | Authentification | Toujours via `auth` | + middleware de rôle |
| Action | Préconditions métier (`vehicle non sorti`, etc.) | Oui | + vérifications de droits par rôle |
| Controller | `$this->authorize()` avant écriture / suppression | Policies stub | Policies réelles |
| Repository | Filtrage par contexte (ex: `whereNull('deleted_at')` par défaut) | Toujours | + filtrage par scope rôle |

---

## Arborescence type — Floty V1

### Principes structurants de la segmentation

La segmentation suit **trois axes** qui s'appliquent partout (back et front) :

1. **Axe Espace** — distingue le contexte d'usage. En V1 Floty :
   - `Web/` — partie publique (accueil, mentions légales, page de connexion).
   - `User/` — partie utilisateur connecté (toutes les fonctionnalités métier après login).
   En V2 si les rôles arrivent, l'axe Espace s'étend naturellement : `User/Admin/`, `User/Operator/`, `User/Reader/`.

2. **Axe Domaine** — entité métier ou famille fonctionnelle (`Vehicle`, `Company`, `Driver`, `Assignment`, `Unavailability`, `Declaration`, `Planning`, `Fiscal`, `Auth`).

3. **Axe Page / Action** (front uniquement) — chaque page ou action significative a son propre dossier qui contient la vue principale **et** ses partials. Plus jamais de fichier de 1000 lignes ni de dossier `Partials/` fourre-tout à la racine.

> **Pourquoi la segmentation par Espace dès V1** : la maintenabilité ne dépend pas du nombre d'espaces aujourd'hui, mais de la capacité à isoler clairement chaque contexte. Préempter `Web/` vs `User/` dès V1 :
>
> - donne au public et au connecté leurs propres dossiers (pas de mélange `HomeController` + `VehicleController`),
> - prépare V2 sans refonte : si un `Admin/` arrive, il s'insère sans rien déplacer,
> - facilite la navigation IDE : le développeur sait exactement où chercher selon le contexte d'usage,
> - évite la pollution des dossiers métier par des fichiers transverses (page d'accueil, mentions légales, etc.).
>
> **Règle directrice** : qui peut le plus peut le moins. Mieux vaut une couche de namespace en plus dès V1 que d'avoir à refactorer 100 fichiers le jour où un second espace utilisateur apparaît.

### Arborescence backend `app/`

```
app/
├── Actions/
│   ├── Web/                                        ← partie publique
│   │   └── Auth/
│   │       └── LoginAction.php
│   └── User/                                       ← partie connectée
│       ├── Vehicle/
│       │   ├── CreateVehicleAction.php
│       │   ├── UpdateVehicleAction.php
│       │   ├── SoftDeleteVehicleAction.php
│       │   └── HardDeleteVehicleAction.php
│       ├── VehicleFiscalCharacteristics/
│       │   ├── CreateNewEffectiveVersionAction.php
│       │   └── CorrectExistingVersionAction.php
│       ├── Company/
│       │   ├── CreateCompanyAction.php
│       │   ├── UpdateCompanyAction.php
│       │   └── DeactivateCompanyAction.php
│       ├── Driver/
│       │   ├── CreateDriverAction.php
│       │   ├── UpdateDriverAction.php
│       │   ├── DeactivateDriverAction.php
│       │   └── ReplaceDriverAction.php
│       ├── Assignment/
│       │   ├── CreateAssignmentAction.php
│       │   ├── BulkSaveWeeklyAssignmentsAction.php
│       │   ├── UpdateAssignmentAction.php
│       │   └── DeleteAssignmentAction.php
│       ├── Unavailability/
│       │   ├── CreateUnavailabilityAction.php
│       │   ├── UpdateUnavailabilityAction.php
│       │   └── CloseUnavailabilityAction.php
│       ├── Declaration/
│       │   ├── CalculateDeclarationAction.php
│       │   ├── ChangeDeclarationStatusAction.php
│       │   ├── GenerateDeclarationPdfAction.php
│       │   └── DetectDeclarationInvalidationAction.php
│       └── Planning/
│           └── (orchestrations transverses planning si besoin)
├── Services/
│   ├── Web/
│   │   └── Auth/
│   │       └── LoginAttemptService.php
│   ├── User/
│   │   ├── Vehicle/
│   │   │   ├── VehicleCreationService.php
│   │   │   ├── VehicleUpdateService.php
│   │   │   └── VehicleFiscalCharacteristicsService.php
│   │   ├── Company/
│   │   │   ├── CompanyCreationService.php
│   │   │   └── CompanyDeactivationService.php
│   │   ├── Driver/
│   │   │   ├── DriverCreationService.php
│   │   │   └── DriverReplacementService.php
│   │   ├── Assignment/
│   │   │   ├── AssignmentConflictResolver.php
│   │   │   ├── BulkAssignmentService.php
│   │   │   └── LcdCumulCalculationService.php
│   │   ├── Unavailability/
│   │   │   └── UnavailabilityService.php
│   │   ├── Declaration/
│   │   │   ├── DeclarationCalculationService.php
│   │   │   ├── DeclarationSnapshotService.php
│   │   │   ├── DeclarationStatusService.php
│   │   │   └── DeclarationInvalidationDetector.php
│   │   └── Planning/
│   │       └── HeatmapAggregationService.php
│   └── Shared/                                     ← cross-espaces (Web ET User)
│       ├── Fiscal/
│       │   ├── FiscalRuleEngine.php
│       │   ├── FiscalRulePipeline.php
│       │   └── FiscalRuleRegistry.php
│       ├── Pdf/
│       │   └── DeclarationPdfRenderer.php
│       ├── Storage/
│       │   └── DeclarationPdfStorage.php
│       └── Cache/
│           └── FiscalCacheTagger.php
├── Repositories/
│   ├── User/
│   │   ├── Vehicle/
│   │   │   ├── VehicleListReadRepository.php
│   │   │   ├── VehicleFiscalCharacteristicsReadRepository.php
│   │   │   └── VehicleFiscalCharacteristicsWriteRepository.php
│   │   ├── Assignment/
│   │   │   ├── LcdCumulReadRepository.php
│   │   │   └── WeeklyAssignmentWriteRepository.php
│   │   ├── Declaration/
│   │   │   ├── DeclarationListReadRepository.php
│   │   │   ├── DeclarationWriteRepository.php
│   │   │   └── DeclarationPdfWriteRepository.php
│   │   └── Planning/
│   │       └── HeatmapReadRepository.php
│   └── Shared/
│       └── Fiscal/
│           └── FiscalRuleRegistryReadRepository.php
├── Contracts/
│   └── Repositories/                              ← miroir strict de Repositories/
│       ├── User/
│       │   ├── Vehicle/
│       │   │   ├── VehicleListReadRepositoryInterface.php
│       │   │   ├── VehicleFiscalCharacteristicsReadRepositoryInterface.php
│       │   │   └── VehicleFiscalCharacteristicsWriteRepositoryInterface.php
│       │   ├── Assignment/
│       │   │   ├── LcdCumulReadRepositoryInterface.php
│       │   │   └── WeeklyAssignmentWriteRepositoryInterface.php
│       │   ├── Declaration/
│       │   │   ├── DeclarationListReadRepositoryInterface.php
│       │   │   ├── DeclarationWriteRepositoryInterface.php
│       │   │   └── DeclarationPdfWriteRepositoryInterface.php
│       │   └── Planning/
│       │       └── HeatmapReadRepositoryInterface.php
│       └── Shared/
│           └── Fiscal/
│               └── FiscalRuleRegistryReadRepositoryInterface.php
├── Data/                                          ← DTO Spatie Data
│   ├── User/
│   │   ├── Vehicle/
│   │   │   ├── VehicleData.php
│   │   │   ├── VehicleListItemData.php
│   │   │   ├── VehicleFormData.php
│   │   │   └── VehicleFiscalCharacteristicsData.php
│   │   ├── Company/
│   │   │   ├── CompanyData.php
│   │   │   ├── CompanyListItemData.php
│   │   │   └── CompanyFormData.php
│   │   ├── Driver/
│   │   │   ├── DriverData.php
│   │   │   └── DriverFormData.php
│   │   ├── Assignment/
│   │   │   ├── AssignmentData.php
│   │   │   └── WeeklyAssignmentData.php
│   │   ├── Unavailability/
│   │   │   ├── UnavailabilityData.php
│   │   │   └── UnavailabilityFormData.php
│   │   ├── Declaration/
│   │   │   ├── DeclarationData.php
│   │   │   ├── DeclarationListItemData.php
│   │   │   ├── DeclarationCalculationResultData.php
│   │   │   └── DeclarationPdfData.php
│   │   └── Planning/
│   │       ├── HeatmapCellData.php
│   │       └── HeatmapGridData.php
│   └── Web/
│       └── (DTO publics si besoin)
├── Models/                                        ← Eloquent (par entité, pas par espace)
│   ├── User.php
│   ├── Vehicle.php
│   ├── VehicleFiscalCharacteristics.php
│   ├── Company.php
│   ├── Driver.php
│   ├── Assignment.php
│   ├── Unavailability.php
│   ├── FiscalRule.php
│   ├── Declaration.php
│   └── DeclarationPdf.php
├── Enums/
│   ├── Vehicle/
│   │   ├── VehicleUserType.php
│   │   ├── EnergySource.php
│   │   ├── HomologationMethod.php
│   │   ├── PollutantCategory.php
│   │   ├── EuroStandard.php
│   │   ├── BodyType.php
│   │   └── FiscalCharacteristicsChangeReason.php
│   ├── Unavailability/
│   │   └── UnavailabilityType.php
│   ├── Declaration/
│   │   ├── DeclarationStatus.php
│   │   └── InvalidationReason.php
│   └── Fiscal/
│       ├── RuleType.php
│       └── TaxType.php
├── Exceptions/                                    ← par domaine métier (pas par espace)
│   ├── Vehicle/
│   │   ├── VehicleCreationException.php
│   │   ├── VehicleUpdateException.php
│   │   ├── VehicleNotFoundException.php
│   │   └── VehicleFiscalCharacteristicsValidationException.php
│   ├── Assignment/
│   │   ├── AssignmentConflictException.php
│   │   └── AssignmentListException.php
│   ├── Declaration/
│   │   ├── DeclarationCalculationException.php
│   │   └── DeclarationPdfGenerationException.php
│   └── Fiscal/
│       └── FiscalRulePipelineException.php
├── Http/
│   ├── Controllers/
│   │   ├── Web/
│   │   │   ├── Home/
│   │   │   │   └── HomeController.php
│   │   │   ├── MentionsLegales/
│   │   │   │   └── MentionsLegalesController.php
│   │   │   └── Auth/
│   │   │       └── LoginController.php
│   │   └── User/
│   │       ├── Dashboard/
│   │       │   └── DashboardController.php
│   │       ├── Vehicle/
│   │       │   └── VehicleController.php
│   │       ├── VehicleFiscalCharacteristics/
│   │       │   └── VehicleFiscalCharacteristicsController.php
│   │       ├── Company/
│   │       │   └── CompanyController.php
│   │       ├── Driver/
│   │       │   └── DriverController.php
│   │       ├── Assignment/
│   │       │   └── AssignmentController.php
│   │       ├── Unavailability/
│   │       │   └── UnavailabilityController.php
│   │       ├── Planning/
│   │       │   ├── WeeklyEntryController.php
│   │       │   ├── HeatmapController.php
│   │       │   ├── ByCompanyController.php
│   │       │   ├── ByVehicleController.php
│   │       │   └── WizardAssignmentController.php
│   │       └── Declaration/
│   │           ├── DeclarationController.php
│   │           ├── GenerateDeclarationPdfController.php
│   │           └── FiscalRuleController.php
│   ├── Requests/
│   │   ├── Web/
│   │   │   └── Auth/
│   │   │       └── LoginRequest.php
│   │   └── User/
│   │       ├── Vehicle/
│   │       │   ├── StoreVehicleRequest.php
│   │       │   └── UpdateVehicleRequest.php
│   │       ├── Company/
│   │       │   ├── StoreCompanyRequest.php
│   │       │   └── UpdateCompanyRequest.php
│   │       └── Assignment/
│   │           └── BulkSaveWeeklyAssignmentsRequest.php
│   └── Middleware/
│       └── HandleInertiaRequests.php
├── Policies/
│   ├── VehiclePolicy.php
│   ├── CompanyPolicy.php
│   ├── DriverPolicy.php
│   └── DeclarationPolicy.php
└── Providers/
    └── RepositoryServiceProvider.php
```

### Arborescence frontend `resources/js/`

```
resources/js/
├── Pages/                                          ← une page Inertia = un dossier
│   ├── Web/                                        ← partie publique
│   │   ├── Home/
│   │   │   ├── Home.vue                            ← vue principale (orchestre les partials)
│   │   │   └── Partials/
│   │   │       ├── Hero.vue
│   │   │       ├── ValueProposition.vue
│   │   │       └── ContactCta.vue
│   │   ├── MentionsLegales/
│   │   │   ├── MentionsLegales.vue
│   │   │   └── Partials/
│   │   │       └── LegalContent.vue
│   │   └── Auth/
│   │       └── Login/
│   │           ├── Login.vue
│   │           └── Partials/
│   │               └── LoginForm.vue
│   └── User/                                       ← partie connectée
│       ├── Dashboard/
│       │   ├── Dashboard.vue
│       │   └── Partials/
│       │       ├── KpiCards.vue
│       │       ├── TaxesEstimateChart.vue
│       │       └── RecentActivity.vue
│       ├── Vehicles/
│       │   ├── Index/                              ← liste des véhicules (« Index » = liste)
│       │   │   ├── Index.vue
│       │   │   └── Partials/
│       │   │       ├── VehicleListHeader.vue
│       │   │       ├── VehicleFilters.vue
│       │   │       ├── VehicleTable.vue
│       │   │       └── VehicleEmptyState.vue
│       │   ├── Show/                               ← fiche d'un véhicule
│       │   │   ├── Show.vue
│       │   │   └── Partials/
│       │   │       ├── VehicleSummary.vue
│       │   │       ├── VehicleFiscalCharacteristicsTable.vue
│       │   │       ├── VehicleFiscalHistoryTimeline.vue
│       │   │       ├── VehicleAssignmentsTimeline.vue
│       │   │       └── VehicleUnavailabilitiesList.vue
│       │   ├── Create/
│       │   │   ├── Create.vue
│       │   │   └── Partials/
│       │   │       └── VehicleForm.vue
│       │   └── Edit/
│       │       ├── Edit.vue
│       │       └── Partials/
│       │           └── VehicleForm.vue              ← peut être partagé via Components/ si réellement identique
│       ├── Companies/
│       │   ├── Index/
│       │   │   ├── Index.vue
│       │   │   └── Partials/
│       │   ├── Show/
│       │   │   ├── Show.vue
│       │   │   └── Partials/
│       │   │       ├── CompanySummary.vue
│       │   │       └── CompanyDriversList.vue
│       │   ├── Create/
│       │   │   ├── Create.vue
│       │   │   └── Partials/
│       │   └── Edit/
│       │       ├── Edit.vue
│       │       └── Partials/
│       ├── Drivers/
│       │   ├── Show/
│       │   │   ├── Show.vue
│       │   │   └── Partials/
│       │   │       ├── DriverSummary.vue
│       │   │       └── DriverReplacementWizard.vue
│       │   └── Edit/
│       │       └── Edit.vue
│       ├── Planning/
│       │   ├── Heatmap/
│       │   │   ├── Heatmap.vue
│       │   │   └── Partials/
│       │   │       ├── HeatmapHeader.vue
│       │   │       ├── HeatmapGrid.vue
│       │   │       ├── HeatmapLegend.vue
│       │   │       └── HeatmapFilters.vue
│       │   ├── WeeklyEntry/
│       │   │   ├── WeeklyEntry.vue
│       │   │   └── Partials/
│       │   │       ├── WeeklyEntryHeader.vue
│       │   │       ├── WeeklyEntryTable.vue
│       │   │       ├── WeeklyEntryRow.vue
│       │   │       └── WeeklyEntryToolbar.vue
│       │   ├── ByCompany/
│       │   │   ├── ByCompany.vue
│       │   │   └── Partials/
│       │   │       ├── CompanySelector.vue
│       │   │       ├── ByCompanyGrid.vue
│       │   │       └── LcdCumulCounter.vue
│       │   └── ByVehicle/
│       │       ├── ByVehicle.vue
│       │       └── Partials/
│       │           ├── VehicleSelector.vue
│       │           └── ByVehicleTimeline.vue
│       └── Declarations/
│           ├── Index/
│           │   ├── Index.vue
│           │   └── Partials/
│           │       ├── DeclarationFilters.vue
│           │       └── DeclarationsTable.vue
│           ├── Show/
│           │   ├── Show.vue
│           │   └── Partials/
│           │       ├── DeclarationSummary.vue
│           │       ├── DeclarationCalculationDetails.vue
│           │       ├── DeclarationPdfHistory.vue
│           │       └── DeclarationInvalidationBadge.vue
│           └── Rules/
│               ├── Rules.vue
│               └── Partials/
│                   ├── RulesYearSelector.vue
│                   └── RuleCard.vue
├── Components/                                      ← composants réutilisables hors page
│   ├── Ui/                                          ← UI Kit Floty (custom design system)
│   │   ├── Button/
│   │   │   ├── Button.vue
│   │   │   └── ButtonGroup.vue
│   │   ├── Input/
│   │   │   ├── TextInput.vue
│   │   │   ├── NumberInput.vue
│   │   │   ├── SelectInput.vue
│   │   │   ├── CheckboxInput.vue
│   │   │   ├── DateInput.vue
│   │   │   └── InputError.vue
│   │   ├── Modal/
│   │   │   ├── Modal.vue
│   │   │   └── ConfirmModal.vue
│   │   ├── Drawer/
│   │   │   └── Drawer.vue
│   │   ├── Toast/
│   │   │   └── Toast.vue
│   │   ├── Badge/
│   │   │   └── Badge.vue
│   │   ├── Card/
│   │   │   └── Card.vue
│   │   └── Table/
│   │       ├── DataTable.vue
│   │       └── DataTableColumn.vue
│   ├── Layouts/                                     ← squelettes de page (top bar, sidebar)
│   │   ├── WebLayout.vue                            ← squelette pages publiques
│   │   ├── UserLayout.vue                           ← squelette pages connectées (sidebar + topbar)
│   │   └── Partials/
│   │       ├── Sidebar.vue
│   │       ├── TopBar.vue
│   │       ├── YearSelector.vue
│   │       └── UserMenu.vue
│   └── Domain/                                      ← composants métier réutilisables cross-pages
│       ├── Vehicle/
│       │   ├── VehicleCard.vue
│       │   └── VehicleStatusBadge.vue
│       ├── Company/
│       │   ├── CompanyBadge.vue                  ← code court coloré
│       │   └── CompanySelector.vue
│       ├── Planning/
│       │   ├── HeatmapCell.vue
│       │   └── WeeklyTableCell.vue
│       └── Declaration/
│           ├── DeclarationStatusBadge.vue
│           └── InvalidationBadge.vue
├── Composables/                                     ← logique réactive partagée
│   ├── User/
│   │   ├── useFiscalYear.ts
│   │   ├── useLcdCumul.ts
│   │   ├── useToast.ts
│   │   └── useWeeklyEntrySelection.ts
│   ├── Web/
│   │   └── useContactForm.ts
│   └── Shared/
│       ├── useDebouncedRef.ts
│       └── useKeyboardShortcuts.ts
├── Stores/                                          ← Pinia (état cross-page UNIQUEMENT)
│   └── User/
│       ├── fiscalYearStore.ts
│       └── currentUserStore.ts
├── Utils/                                           ← fonctions pures, sans état
│   ├── format/
│   │   ├── formatEuro.ts
│   │   ├── formatDate.ts
│   │   ├── formatImmatriculation.ts
│   │   └── formatSiren.ts
│   ├── validation/
│   │   ├── frenchPlate.ts
│   │   └── sirenChecksum.ts
│   └── fiscal/
│       └── computeProrata.ts                        ← uniquement pour affichage estimatif côté client (jamais pour calcul officiel)
├── Layouts/                                         ← layouts Inertia (référencés dans defineLayout)
│   ├── WebLayout.vue                                ← (alias possible vers Components/Layouts/WebLayout.vue)
│   └── UserLayout.vue
├── types/
│   ├── generated.d.ts                               ← types TS auto-générés par Spatie TS Transformer (NE PAS éditer)
│   ├── inertia.d.ts                                 ← typage des shared props Inertia
│   └── env.d.ts                                     ← typage des variables d'environnement Vite
└── app.ts                                           ← entrée Inertia (createInertiaApp + plugins)

resources/css/
└── app.css                                          ← Tailwind v4 + tokens design system Floty (@theme)
```

### Règles d'arborescence — points clés

| Règle | Pourquoi |
|---|---|
| **Espace en premier** (`Web/` ou `User/`) puis Domaine, sauf `Models/`, `Enums/`, `Exceptions/` (par domaine pur) | Modèles et exceptions sont conceptuellement transverses ; les segmenter par espace n'apporterait rien |
| **Une page = un dossier** : `Pages/User/Vehicles/Index/Index.vue` + `Index/Partials/*.vue` | Permet de découper une vue de 1000 lignes en 5 partials de 200 sans polluer un dossier `Partials/` global |
| **Nom du dossier de page = nom de la vue principale** (`Index/Index.vue`, `Show/Show.vue`) | Convention claire : on sait où chercher la vue qui orchestre |
| **`Index` peut devenir `List` quand cela lève toute ambiguïté** (page « Liste des véhicules ») | Tolérance pour la lisibilité ; règle générale : laisser `Index` |
| **Composants réutilisables HORS page** : `Components/Domain/{Domaine}/` ou `Components/Ui/` ou `Components/Layouts/` | Si un composant est spécifique à une page, il vit dans son dossier `Partials/` ; sinon dans `Components/` |
| **`Components/Ui/`** : c'est l'UI Kit custom Floty (boutons, inputs, modals, etc.). Construit depuis le design system. Pas de shadcn-vue. | Maîtrise totale, cohérence visuelle, pas de dépendance tierce |
| **`Components/Domain/`** : composants métier transverses (`VehicleCard.vue` utilisé sur Index ET Dashboard) | Ce qui n'est pas spécifique à une page mais l'est à un domaine |
| **`Composables/{Espace}/`** : logique réactive (état + watchers + lifecycle) | Comme `useForm`, mais pour Floty (`useFiscalYear`, `useLcdCumul`) |
| **`Utils/`** : fonctions pures stateless (formatage, validation) | Pas de Vue, pas de réactif, testable en isolation |
| **`Stores/`** : Pinia. À utiliser **uniquement** pour de l'état persistant cross-pages. Tout le reste va en composables ou en props Inertia | Anti-pattern : « store global fourre-tout ». Voir `pinia-stores.md`. |
| **`types/generated.d.ts`** : NE PAS éditer manuellement | Régénéré par `php artisan typescript:transform` |

### Évolution V2

L'arborescence V1 préempte volontairement l'évolution V2. Si V2 introduit des rôles (admin métier, opérateur, lecteur), la segmentation s'étend **par addition pure**, sans déplacer les fichiers V1 :

- `app/Actions/User/{Role}/{Domaine}/...` (ex: `app/Actions/User/Admin/FiscalRule/`)
- `resources/js/Pages/User/{Role}/{Domaine}/...`

Les fichiers V1 restent dans `User/{Domaine}/` (qui devient implicitement « tout user authentifié sans distinction »), et les nouveaux rôles s'ajoutent à côté. **Aucune décision V1 ne bloque V2.**

---

## Résumé des conventions

> Toutes les couches, sauf `Models/`, `Enums/`, `Exceptions/`, suivent la double segmentation **`{Espace}/{Domaine}/`** où `{Espace}` ∈ {`Web`, `User`, `Shared`} et `{Domaine}` est l'entité métier ou la famille fonctionnelle.

### Couche présentation (front)

| Couche | Emplacement | Nommage | Rôle |
|---|---|---|---|
| Page Inertia | `resources/js/Pages/{Espace}/{Domaine}/{PageName}/` | `{PageName}.vue` (`Index`, `Show`, `Edit`, `WeeklyEntry`) + dossier `Partials/` adjacent | Page complète, props typées, partials par page |
| Partial de page | `resources/js/Pages/{Espace}/{Domaine}/{PageName}/Partials/` | `PascalCase.vue` | Sous-bloc spécifique à une page |
| Composant Domain | `resources/js/Components/Domain/{Domaine}/` | `PascalCase.vue` (`VehicleCard.vue`) | Composant métier réutilisable cross-pages |
| Composant UI Kit | `resources/js/Components/Ui/{Famille}/` | `PascalCase.vue` (`Button.vue`) | Composant design system custom Floty |
| Layout | `resources/js/Components/Layouts/` (+ alias `resources/js/Layouts/`) | `{Espace}Layout.vue` (`UserLayout.vue`, `WebLayout.vue`) | Squelette de page (sidebar, top bar) |
| Composable | `resources/js/Composables/{Espace}/` | `useXxx.ts` (`useFiscalYear.ts`) | Logique réactive partagée |
| Store Pinia | `resources/js/Stores/{Espace}/` | `xxxStore.ts` (`fiscalYearStore.ts`) | État global cross-page (rare) |
| Utility | `resources/js/Utils/{Famille}/` | `camelCase.ts` (`formatEuro.ts`) | Fonction pure stateless |
| Type généré | `resources/js/types/generated.d.ts` | (auto) | Types TS issus des Data Spatie — NE PAS éditer |

### Couche présentation (back)

| Couche | Emplacement | Nommage | Rôle |
|---|---|---|---|
| FormRequest | `app/Http/Requests/{Espace}/{Domaine}/` | `{Verbe}{Entité}Request` (`StoreVehicleRequest`) | Validation déclarative |
| Controller | `app/Http/Controllers/{Espace}/{Domaine}/` | `{Entité}Controller` ou `{Verbe}{Entité}Controller` (invokable) | Point d'entrée HTTP, délégation, réponse Inertia |
| Resource (DTO sortie) | `app/Data/{Espace}/{Domaine}/` | `{Entité}{Usage?}Data` | Contrat front/back, immutable, type TS auto |

### Couches métier

| Couche | Emplacement | Nommage | Rôle |
|---|---|---|---|
| Action | `app/Actions/{Espace}/{Domaine}/` | `{Verbe}{Entité}Action` | Orchestration, transactions |
| Service | `app/Services/{Espace}/{Domaine}/` ou `app/Services/Shared/{Domaine}/` | `{Entité}{Responsabilité}Service` | Logique métier |
| Repository | `app/Repositories/{Espace}/{Entité}/` ou `app/Repositories/Shared/{Domaine}/` | `{Entité}{Responsabilité}Repository` | Interaction BDD complexe |
| Interface | `app/Contracts/Repositories/{Espace}/{Entité}/` (miroir strict) | `{Entité}{Responsabilité}RepositoryInterface` | Contrat repository |

### Couches transverses (sans segmentation par espace)

| Couche | Emplacement | Nommage | Rôle |
|---|---|---|---|
| Model | `app/Models/` | `{Entité}` (singulier, PascalCase) | Représentation Eloquent |
| Exception | `app/Exceptions/{Domaine}/` | `{Entité}{Contexte}Exception` | Erreur métier typée |
| Enum | `app/Enums/{Domaine}/` | `{Entité}{Concept}` en anglais (`EnergySource`, `DeclarationStatus`) | Énumération typée |
| Provider | `app/Providers/` | `{Sujet}ServiceProvider` (`RepositoryServiceProvider`) | Binding interfaces → implémentations |
| Policy | `app/Policies/` | `{Entité}Policy` | Autorisation Laravel |

---

## Cohérence avec les autres règles

- **Structure des fichiers** (front + back complet, par zone et par domaine) : voir `structure-fichiers.md`.
- **Conventions de nommage** (PHP, TypeScript, Vue, BDD) : voir `conventions-nommage.md`.
- **Gestion des assets Vite** (entry, bundles, code splitting) : voir `assets-vite.md`.
- **Gestion des erreurs** (exceptions typées, propagation, affichage utilisateur) : voir `gestion-erreurs.md`.
- **Navigation Inertia** (router, useForm, partial reloads, anti-patterns) : voir `inertia-navigation.md`.
- **Composants Vue** (Composition API, props/emits/slots typés, anti-patterns) : voir `vue-composants.md`.
- **DTO et TypeScript** (Spatie Data + TS Transformer, génération auto, conventions) : voir `typescript-dto.md`.
- **Composables et utils côté front** : voir `composables-services-utils.md`.
- **Stores Pinia** (quand, comment, anti-patterns) : voir `pinia-stores.md`.
- **Performance UI** (memoization, virtualisation, pièges skeleton/lazy-loading) : voir `performance-ui.md`.
- **Tests frontend** (Vitest, Vue Test Utils, fixtures typées) : voir `tests-frontend.md`.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 2.2 | 24/04/2026 | Micha MEGRET | Passe d'audit (étape 5.6) — A1 corrigé : alignement des sections 3 à 7 sur la convention de segmentation par espace `{Espace}/{Domaine}/`. Tous les exemples (namespaces, `Inertia::render`, références de routes) mis à jour pour utiliser `User/Vehicle`, `User/Attribution`, `User/Declaration`, `Shared/Pdf`, `Shared/Storage`, etc. Suppression de la note ligne 228-230 qui contredisait la décision finale. Mise à jour de la convention enum `TypeUtilisateur` → `VehicleUserType` (cf. anomalie mineure E1, anglais strict). Mention « PHP 8.4+, en pratique PHP 8.5 sur Floty ». Suppression des « (à créer) » obsolètes. |
| 2.1 | 24/04/2026 | Micha MEGRET | Révision après relecture client : (1) ajout sous-section « Transactions et pragmatisme » pour clarifier le couplage entre besoin transactionnel et présence de la couche Action, (2) ajout sous-section « Qui orchestre quoi ? Action vs Service face aux Repositories » avec règle par défaut (Service → Repo) et 3 cas justifiant l'Approche B (Action → Repo direct), (3) refonte de l'exemple `VehicleFiscalCharacteristicsService` pour montrer une vraie logique métier (validation invariants WLTP↔CO2, hybride↔moteur sous-jacent, calcul effective_to, exceptions typées) et plus un proxy Eloquent, (4) ajout section « Conformité de la pratique aux standards seniors » sur Spatie Data + génération auto TS, (5) refonte complète de l'arborescence avec **segmentation par espace dès V1** (`Web/` public + `User/` connecté), partials par page (`Pages/{Espace}/{Domaine}/{PageName}/Partials/`), Services/Repositories/Data complétés pour tous les domaines, ajout `Components/Domain/`, `Components/Layouts/`, `Composables/{Espace}/`, (6) note explicative `GenerateDeclarationPdfAction` justifiant la persistance directe du `DeclarationPdf::create` par pragmatisme, (7) note d'évolution V2 reformulée — extension par addition pure, sans déplacement de fichiers V1. |
| 2.0 | 24/04/2026 | Micha MEGRET | **Refonte complète** pour stack Floty (Laravel 13 + Inertia v3 + Vue 3 + TypeScript 6 + Spatie Data + PHP 8.5). Suppression de Livewire, ajout de la 5ᵉ couche Resource (DTO Spatie Data) sur le chemin de remontée, ajout d'une section dédiée Composant Vue + Inertia Router côté front, exemples métier Floty (véhicule, attribution, déclaration), arborescence V1 complète avec `resources/js/`, syntaxe PHP 8.5 (`final readonly class`, constructor property promotion), références croisées vers les futures règles Inertia/Vue/TS. |
| 1.0 | mars 2026 | Micha MEGRET | Version initiale, contexte ancien projet Livewire + Alpine + Blade. |
