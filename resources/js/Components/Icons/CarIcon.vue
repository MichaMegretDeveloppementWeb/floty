<script setup lang="ts">
/**
 * Custom car icon (Phosphor « regular » car). The base glyph is a thin filled
 * contour, so to match the visual weight of the lucide stroke icons around it we
 * paint a same-color stroke on top of the fill (`STROKE`, in the icon's own
 * 0..256 viewBox space, so it scales with `size`).
 *
 * `strokeWidth` is declared only to absorb the lucide-scaled value the sidebar
 * passes to every icon (`:stroke-width="1.75"`): without this prop it would fall
 * through to the <svg> and override our bold stroke, making the car thin again.
 */
const STROKE = 5;

const props = withDefaults(
    defineProps<{
        size?: number | string;
        strokeWidth?: number | string;
    }>(),
    {
        size: 24,
        strokeWidth: 2,
    },
);
</script>

<template>
    <svg
        :width="props.size"
        :height="props.size"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 256 256"
        fill="currentColor"
        stroke="currentColor"
        :stroke-width="STROKE"
        stroke-linejoin="round"
        stroke-linecap="round"
    ><path d="M240,114H210.49l-43.9-43.9a13.94,13.94,0,0,0-9.9-4.1H44.28a14,14,0,0,0-11.65,6.23L3,116.67A6,6,0,0,0,2,120v48a14,14,0,0,0,14,14H34.6a30,30,0,0,0,58.8,0h69.2a30,30,0,0,0,58.8,0H240a14,14,0,0,0,14-14V128A14,14,0,0,0,240,114ZM42.62,78.89A2,2,0,0,1,44.28,78H156.69a2,2,0,0,1,1.41.59L193.52,114H19.21ZM64,194a18,18,0,1,1,18-18A18,18,0,0,1,64,194Zm128,0a18,18,0,1,1,18-18A18,18,0,0,1,192,194Zm50-26a2,2,0,0,1-2,2H221.4a30,30,0,0,0-58.8,0H93.4a30,30,0,0,0-58.8,0H16a2,2,0,0,1-2-2V126H240a2,2,0,0,1,2,2Z"></path></svg>
</template>
