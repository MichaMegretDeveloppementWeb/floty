<script setup lang="ts">
/**
 * Dropdown générique « Actions » pour cellules de tableau ou cartes
 * (chantier B-bis). Affiche un trigger compact et révèle les actions
 * via un menu **téléporté vers `body`** + position fixed — évite tout
 * clipping par les `overflow-auto` parents (tables, cards). Pattern
 * inspiré de `Tooltip.vue:22-60`.
 *
 * Usage :
 *   <ActionsMenu>
 *       <button @click="...">Éditer</button>
 *       <button @click="..." class="text-rose-600">Détacher</button>
 *   </ActionsMenu>
 *
 * Slots :
 *   - `trigger` (optionnel) : remplace le bouton par défaut (icône `MoreVertical`)
 *   - default : les `<button>` du menu (chacun ferme le menu en cliquant)
 *
 * Props :
 *   - `align` (`'left' | 'right'`, défaut `right`) : alignement du menu
 *     par rapport au trigger.
 *   - `width` (px ou string CSS, défaut `192px`) : largeur du menu.
 *   - `triggerLabel` (string, défaut `Actions`) : aria-label du bouton.
 */
import { MoreVertical } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        align?: 'left' | 'right';
        width?: string;
        triggerLabel?: string;
    }>(),
    {
        align: 'right',
        width: '192px',
        triggerLabel: 'Actions',
    },
);

const open = ref<boolean>(false);
const triggerRef = ref<HTMLElement | null>(null);
const menuRef = ref<HTMLElement | null>(null);
const top = ref<number>(0);
const left = ref<number>(0);

function updatePosition(): void {
    const trigger = triggerRef.value;
    const menu = menuRef.value;

    if (!trigger || !menu) {
        return;
    }

    const triggerRect = trigger.getBoundingClientRect();
    const menuRect = menu.getBoundingClientRect();
    const margin = 4;

    // Ouvre par défaut sous le trigger. Si pas la place en bas (proche du
    // viewport bottom), bascule au-dessus.
    const fitsBelow = triggerRect.bottom + menuRect.height + margin <= window.innerHeight;
    top.value = fitsBelow
        ? triggerRect.bottom + margin
        : triggerRect.top - menuRect.height - margin;

    if (props.align === 'right') {
        left.value = triggerRect.right - menuRect.width;
    } else {
        left.value = triggerRect.left;
    }

    // Clamp horizontal pour éviter de sortir du viewport.
    if (left.value < margin) {
        left.value = margin;
    } else if (left.value + menuRect.width > window.innerWidth - margin) {
        left.value = window.innerWidth - menuRect.width - margin;
    }
}

async function toggle(event: MouseEvent): Promise<void> {
    event.stopPropagation();
    open.value = !open.value;

    if (open.value) {
        // Attendre le rendu avant de mesurer la taille du menu.
        await new Promise((resolve) => requestAnimationFrame(resolve));
        updatePosition();
    }
}

function close(): void {
    open.value = false;
}

function handleDocumentMouseDown(event: MouseEvent): void {
    if (!open.value) {
        return;
    }

    const target = event.target as Node | null;
    if (target === null) {
        return;
    }

    if (
        (triggerRef.value !== null && triggerRef.value.contains(target))
        || (menuRef.value !== null && menuRef.value.contains(target))
    ) {
        return;
    }

    close();
}

function handleEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape' && open.value) {
        close();
    }
}

function handleScroll(): void {
    if (open.value) {
        updatePosition();
    }
}

// Ferme le menu après un clic interne (les `<button>` du slot émettent
// `click` qui bubble jusqu'à la racine du menu).
function handleMenuClick(): void {
    close();
}

onMounted(() => {
    document.addEventListener('mousedown', handleDocumentMouseDown);
    document.addEventListener('keydown', handleEscape);
    window.addEventListener('scroll', handleScroll, true);
    window.addEventListener('resize', handleScroll);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleDocumentMouseDown);
    document.removeEventListener('keydown', handleEscape);
    window.removeEventListener('scroll', handleScroll, true);
    window.removeEventListener('resize', handleScroll);
});

watch(open, (isOpen) => {
    if (isOpen) {
        // Re-mesure quand le contenu du slot change après ouverture
        // (cas rare mais protège contre les rehydration).
        updatePosition();
    }
});
</script>

<template>
    <span ref="triggerRef" class="inline-flex">
        <slot name="trigger" :toggle="toggle" :open="open">
            <button
                type="button"
                :aria-label="triggerLabel"
                :aria-expanded="open"
                aria-haspopup="menu"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition-colors duration-[120ms] ease-out hover:bg-slate-100 hover:text-slate-700 focus-visible:ring-2 focus-visible:ring-slate-200 focus-visible:outline-none"
                @click="toggle"
            >
                <MoreVertical :size="16" :stroke-width="1.75" />
            </button>
        </slot>
    </span>

    <Teleport to="body">
        <div
            v-if="open"
            ref="menuRef"
            role="menu"
            class="fixed z-[1000] overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
            :style="{ top: `${top}px`, left: `${left}px`, width: width }"
            @click="handleMenuClick"
        >
            <slot />
        </div>
    </Teleport>
</template>

<style scoped>
:slotted(button) {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    text-align: left;
    font-size: 0.875rem;
    color: rgb(51 65 85); /* slate-700 */
    transition: background-color 120ms ease-out, color 120ms ease-out;
    cursor: pointer;
}

:slotted(button:hover) {
    background-color: rgb(248 250 252); /* slate-50 */
    color: rgb(15 23 42); /* slate-900 */
}

:slotted(button.danger) {
    color: rgb(225 29 72); /* rose-600 */
}

:slotted(button.danger:hover) {
    background-color: rgb(255 241 242); /* rose-50 */
    color: rgb(190 18 60); /* rose-700 */
}

:slotted(button:disabled) {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
