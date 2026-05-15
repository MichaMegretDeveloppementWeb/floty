/**
 * Indexe une liste d'objets par leur `id` numérique pour des lookups
 * O(1) (plus efficace qu'`Object.fromEntries(...)` ou un `array.find`
 * répété).
 *
 * Mutualisé Lot 7 D07 (F-40-011) · ~5 sites consommateurs avant
 * extraction · pattern dupliqué `new Map(items.map(v => [v.id, v]))`
 * dans `Contracts/Create/Index.vue`, `ContractFormFields.vue`,
 * `Contracts/Edit/Index.vue`, `WeekDrawer/ContractForm.vue`.
 *
 * Usage typique avec un `computed` Vue ·
 *   const vehicleMap = computed(() => indexById(props.vehicles));
 *   const v = vehicleMap.value.get(vehicleId);
 */
export function indexById<T extends { id: number }>(items: readonly T[]): Map<number, T> {
    return new Map(items.map((item) => [item.id, item]));
}
