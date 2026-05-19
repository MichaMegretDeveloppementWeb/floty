/**
 * Index a list of objects by their numeric `id` for O(1) lookups (more
 * efficient than `Object.fromEntries(...)` or repeated `array.find`).
 *
 * Typical usage with a Vue `computed`:
 *   const vehicleMap = computed(() => indexById(props.vehicles));
 *   const v = vehicleMap.value.get(vehicleId);
 */
export function indexById<T extends { id: number }>(items: readonly T[]): Map<number, T> {
    return new Map(items.map((item) => [item.id, item]));
}
