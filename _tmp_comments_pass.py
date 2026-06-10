import io

def patch(path, pairs):
    s = io.open(path, encoding='utf-8').read()
    for old, new in pairs:
        assert old in s, f'{path}: NOT FOUND >>> {old[:80]}'
        s = s.replace(old, new, 1)
    io.open(path, 'w', encoding='utf-8', newline='\n').write(s)

# 1. Resolver: 14-line class doc -> concise
patch('app/Services/VehicleEvent/EventNatureFiscalResolver.php', [(
"""/**
 * Decides whether a set of event natures makes the event fiscally reductive:
 * true as soon as ONE nature matches a label of the frozen reductive block.
 * Free entries and every other nature are non-reductive by default.
 *
 * The frozen block lives in the CODE ({@see EventNatureCatalog::REDUCTIVE},
 * fiscal source of truth); the `vehicle_event_natures.is_fiscally_reductive`
 * rows are its seeded mirror for the UI suggestions. The resolver matches
 * against the UNION of both so the fiscal correctness of writes never
 * depends on the seeder having run (e.g. a deploy where
 * `db:seed VehicleEventNatureSeeder` was forgotten).
 *
 * Matching is trimmed and case-insensitive, the same normalisation as the
 * nature list composition ({@see App\\Support\\VehicleEvent\\EventCategoryList}).
 * The result feeds the write-time denormalisation of
 * `vehicle_events.has_fiscal_impact`; the fiscal rules (R-20XX-008) keep
 * reading that frozen boolean only.
 */""",
"""/**
 * True when one nature matches the frozen reductive block (trim +
 * case-insensitive). Matches the UNION of the DB rows and
 * {@see EventNatureCatalog::REDUCTIVE} so fiscal correctness never depends
 * on the seeder having run; feeds the frozen `has_fiscal_impact` boolean.
 */""")])

# 2. Detail suggestion controller
patch('app/Http/Controllers/User/VehicleEvent/VehicleEventDetailSuggestionController.php', [(
"""    /**
     * « Ajouter à la liste » (section « Détails » of the event form): persists
     * a free detail line as a future suggestion. Direct repository call: a
     * single idempotent insert, no orchestration to delegate. The form
     * partial-reloads its `detailSuggestions` prop on success.
     */""",
"""    /**
     * Persists a free detail line as a future suggestion (idempotent insert,
     * no Action: nothing to orchestrate).
     */"""), (
"""    /**
     * Removes a detail suggestion (the « x » of the suggestion list). The
     * whole catalogue is user-managed; events keep their attached detail
     * lines untouched.
     */""",
"""    /**
     * Removes a suggestion; events keep their attached detail lines.
     */""")])

# 3. Models
patch('app/Models/VehicleEventDetail.php', [(
"""/**
 * One free detail line attached to a vehicle event (section « Détails »:
 * « Vidange », « Changement courroie »...). Free text with autocomplete from
 * {@see VehicleEventDetailSuggestion}; `UNIQUE(vehicle_event_id, detail)`
 * enforces intra-event dedup.
 *
 * @property int $id""",
"""/**
 * One free detail line of a vehicle event; UNIQUE(vehicle_event_id, detail).
 *
 * @property int $id"""), (
"""    /**
     * Owning event.
     *
     * @return BelongsTo<VehicleEvent, $this>
     */""",
"""    /**
     * @return BelongsTo<VehicleEvent, $this>
     */""")])

patch('app/Models/VehicleEventDetailSuggestion.php', [(
"""/**
 * One autocomplete suggestion for the event « Détails » lines. Entirely
 * user-managed (« Ajouter à la liste » / retrait), no frozen base: deleting
 * a suggestion never touches the detail lines already attached to events.
 *
 * @property int $id""",
"""/**
 * User-managed autocomplete suggestion for the event detail lines.
 *
 * @property int $id""")])

# 4. Read repo interface
patch('app/Contracts/Repositories/User/VehicleEvent/VehicleEventReadRepositoryInterface.php', [(
"""    /**
     * All unavailabilities of a vehicle (excluding soft-deleted),
     * sorted by `start_date DESC`, eager documents + natures. Consumed by
     * the vehicle Edit page and the contract fiscal breakdown, which never
     * render the « Détails » lines (strict per-screen loading: the timeline
     * uses {@see findForVehicleTimeline()} instead).
     *
     * @return Collection<int, VehicleEvent>
     */
    public function findForVehicle(int $vehicleId): Collection;

    /**
     * Same set with the « Détails » lines eager on top (documents + natures
     * + details), for the vehicle Show page whose events tab renders them.
     *
     * @return Collection<int, VehicleEvent>
     */
    public function findForVehicleTimeline(int $vehicleId): Collection;""",
"""    /**
     * Vehicle events sorted `start_date DESC`, eager documents + natures
     * (screens that never render the detail lines).
     *
     * @return Collection<int, VehicleEvent>
     */
    public function findForVehicle(int $vehicleId): Collection;

    /**
     * Same set with the detail lines eager on top (vehicle Show timeline).
     *
     * @return Collection<int, VehicleEvent>
     */
    public function findForVehicleTimeline(int $vehicleId): Collection;""")])

# 5. Read repo impl comments
patch('app/Repositories/User/VehicleEvent/VehicleEventReadRepository.php', [(
"""            // Garage and postal code are intentionally NOT part of the free
            // search (they clash with license plates): they have their own
            // dedicated filters below.
            $query->where(""",
"""            // Garage / postal code stay out of the free search (they clash
            // with license plates): dedicated filters below.
            $query->where("""), (
"""    /**
     * Distinct garage names already recorded on events, alphabetical. Feeds
     * the form autocomplete: the list grows automatically with every saved
     * event that carries a garage (no manual « Ajouter à la liste »).
     *
     * @return list<string>
     */""",
"""    /**
     * Distinct garages recorded on events, alphabetical (index-only scan).
     *
     * @return list<string>
     */""")])

# 6. Write repo
patch('app/Repositories/User/VehicleEvent/VehicleEventWriteRepository.php', [(
"""    /**
     * Replace the event's detail lines with the given (already composed /
     * deduped) list, in order.
     *
     * @param  list<string>  $details
     */""",
"""    /**
     * Replaces the event's detail rows with the composed list, in order.
     *
     * @param  list<string>  $details
     */""")])

# 7. Detail suggestion DTO
patch('app/Data/User/VehicleEvent/StoreVehicleEventDetailSuggestionData.php', [(
"""/**
 * « Ajouter à la liste » payload of the « Détails » section: persists a free
 * detail line as a future suggestion. The 100-char cap mirrors
 * `vehicle_event_detail_suggestions.label` / `vehicle_event_details.detail`.
 */""",
"""/**
 * « Ajouter à la liste » payload of the detail suggestions (100 = DB cap).
 */""")])

# 8. Detail suggestion repos
patch('app/Contracts/Repositories/User/VehicleEvent/VehicleEventDetailSuggestionReadRepositoryInterface.php', [(
"""/**
 * Reads on the detail-suggestion catalogue (`vehicle_event_detail_suggestions`).
 *
 * No transformation, no DTO composition (R3) · returns primitive lists.
 */""",
"""/**
 * Reads on the detail-suggestion catalogue (primitive lists, R3).
 */"""), (
"""    /**
     * Every suggestion with its id, alphabetical. The whole catalogue is
     * user-managed: every entry is also deletable.
     *
     * @return list<array{id: int, label: string}>
     */""",
"""    /**
     * Every suggestion with its id, alphabetical (all deletable).
     *
     * @return list<array{id: int, label: string}>
     */""")])

patch('app/Contracts/Repositories/User/VehicleEvent/VehicleEventDetailSuggestionWriteRepositoryInterface.php', [(
"""    /**
     * Persists a detail line as a future suggestion (« Ajouter à la liste »).
     * Idempotent: an existing entry matching the label (trimmed,
     * case-insensitive) short-circuits the insert.
     */
    public function addSuggestion(string $label): void;

    /**
     * Deletes a suggestion. Events keep their attached detail lines untouched
     * (they live in `vehicle_event_details`).
     */
    public function deleteSuggestion(VehicleEventDetailSuggestion $suggestion): void;""",
"""    /**
     * Persists a detail line as a suggestion (idempotent, case-insensitive).
     */
    public function addSuggestion(string $label): void;

    /**
     * Deletes a suggestion; events keep their attached detail lines.
     */
    public function deleteSuggestion(VehicleEventDetailSuggestion $suggestion): void;""")])

# 9. Migrations
patch('database/migrations/2026_06_10_150215_create_vehicle_event_details_tables.php', [(
"""/**
 * Section « Details » des evenements vehicule :
 *   - `vehicle_event_details` : une ligne de detail libre par row (ex.
 *     « Vidange », « Changement courroie »), rattachee a son evenement,
 *     dedupliquee intra-evenement (UNIQUE) ;
 *   - `vehicle_event_detail_suggestions` : catalogue d'autocompletion gere
 *     par l'utilisateur (« Ajouter a la liste » / retrait), sans bloc fige.
 */""",
"""/**
 * Event detail lines (one row per line, UNIQUE per event) and their
 * user-managed autocomplete suggestion catalogue.
 */""")])

patch('database/migrations/2026_06_10_162209_add_garage_and_postal_code_to_vehicle_events.php', [(
"""/**
 * Garage (nom libre) et code postal facultatifs sur les evenements vehicule.
 * Le garage alimente automatiquement l'autocompletion (DISTINCT des valeurs
 * presentes, pas de table catalogue) ; les deux champs sont recherchables
 * (liste globale + timeline vehicule).
 */""",
"""/**
 * Optional garage name and postal code on vehicle events.
 */""")])

patch('database/migrations/2026_06_10_173951_add_garage_index_to_vehicle_events.php', [(
"""/**
 * Index sur `garage` : la liste d'autosuggestion des filtres lit
 * `SELECT DISTINCT garage` a chaque affichage (formulaire + index global),
 * l'index transforme ce scan en parcours d'index borne par le nombre de
 * garages distincts.
 */""",
"""/**
 * Serves the `SELECT DISTINCT garage` autosuggestion as an index-only scan.
 */""")])

# 10. Seeders
patch('database/seeders/VehicleEventDetailSuggestionSeeder.php', [(
"""/**
 * Liste de départ des suggestions de la section « Détails » des événements
 * (interventions courantes d'entretien, freinage, pneumatiques, contrôle,
 * carrosserie). `updateOrCreate` sur le label : un re-seed (prod compris) ne
 * crée aucun doublon et préserve les entrées ajoutées par l'utilisateur,
 * comme pour les natures.
 */""",
"""/**
 * Starter list of event detail suggestions; updateOrCreate keeps re-seeds
 * duplicate-free and preserves user entries.
 */""")])

patch('database/seeders/DemoSeeder.php', [(
"""        // Hard guard: this seeder purges and replaces business data (events,
        // contracts, vehicles...). Running it against production would
        // destroy real client data, so it fails loudly there · use the
        // targeted seeders (natures, détails, fiscal rules) instead.
        if (app()->isProduction()) {""",
"""        // Purges and replaces business data: must fail loudly in production.
        if (app()->isProduction()) {""")])

# 11. Routes
patch('routes/user.php', [(
"""        // Catalogue des suggestions de la section « Détails » des événements
        // (entièrement géré par l'utilisateur : ajout + retrait).
        Route::post('/vehicle-event-detail-suggestions'""",
"""        // User-managed suggestion catalogue of the event detail lines.
        Route::post('/vehicle-event-detail-suggestions'""")])

print('php ok')
