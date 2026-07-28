<script setup lang="ts">
/**
 * GamePicker — selectable row of game chips. The selected chip is bordered and
 * glows in the game's own identity color (same color map as GameBadge).
 */
import GameBadge from '@/Components/GameBadge.vue';

interface GameOption {
    id: number;
    name: string;
    slug: string;
}

defineProps<{
    gameTypes: GameOption[];
    /** slug of the currently selected game */
    modelValue: string;
}>();

const emit = defineEmits<{ (e: 'select', gameType: GameOption): void }>();

// Identity colors — mirror of GameBadge's MAP.
const TOKENS: Record<string, string> = {
    'family-feud': '--color-warning',
    'america-says': '--color-text',
    oodles: '--color-danger',
    scorekeeper: '--color-danger',
    propoff: '--color-success',
    shotmadness: '--color-warning',
};
const selectedStyle = (slug: string) => {
    const t = TOKENS[slug] ?? '--color-text-muted';
    return {
        borderColor: `rgb(var(${t}))`,
        backgroundColor: `rgb(var(${t}) / 0.08)`,
        boxShadow: `0 0 0 1px rgb(var(${t})), 0 0 20px rgb(var(${t}) / 0.35)`,
    };
};
</script>

<template>
    <div class="flex flex-wrap gap-3">
        <button
            v-for="gt in gameTypes"
            :key="gt.id"
            type="button"
            class="flex items-center gap-3 rounded-lg border px-4 py-3 transition"
            :class="gt.slug === modelValue
                ? 'text-body'
                : 'border-border bg-surface-inset text-muted hover:border-border-strong hover:text-body'"
            :style="gt.slug === modelValue ? selectedStyle(gt.slug) : {}"
            @click="emit('select', gt)"
        >
            <GameBadge :slug="gt.slug" />
            <span class="font-semibold">{{ gt.name }}</span>
        </button>
    </div>
</template>
