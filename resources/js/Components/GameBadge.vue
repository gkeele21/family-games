<script setup lang="ts">
/**
 * Neon game badge — a per-game shape in the game's identity color.
 *   variant="token" → dark chip with accent ring + glow (Active / Recent lists)
 *   variant="bare"  → shape only, no chip (the Party Games "Includes" list)
 *
 * Identity colors (gold is reserved for the trophy, so it is never used here):
 *   Family Feud → orange · America Says → white · Oodles → red
 *   Score Keeper → red · PropOff → green · ShotMadness → orange
 */
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        slug: string;
        variant?: 'token' | 'bare';
    }>(),
    { variant: 'token' },
);

type Cfg = { token: string; shape: 'buzzer' | 'bubble' | 'sparkle' | 'tally' | 'diamond' | 'basketball' };

const MAP: Record<string, Cfg> = {
    'family-feud': { token: '--color-warning', shape: 'buzzer' },
    'america-says': { token: '--color-text', shape: 'bubble' },
    oodles: { token: '--color-danger', shape: 'sparkle' },
    scorekeeper: { token: '--color-danger', shape: 'tally' },
    propoff: { token: '--color-success', shape: 'diamond' },
    shotmadness: { token: '--color-warning', shape: 'basketball' },
};

const cfg = computed<Cfg>(() => MAP[props.slug] ?? { token: '--color-text-muted', shape: 'diamond' });
</script>

<template>
    <span
        class="badge"
        :class="variant"
        :style="{ '--ac': `var(${cfg.token})` }"
    >
        <svg viewBox="0 0 48 48" aria-hidden="true">
            <template v-if="cfg.shape === 'buzzer'">
                <g fill="currentColor">
                    <rect x="9" y="28" width="30" height="9" rx="3" />
                    <path d="M13 28a11 11 0 0 1 22 0z" />
                    <rect x="21" y="11" width="6" height="9" rx="3" />
                </g>
            </template>
            <path
                v-else-if="cfg.shape === 'bubble'"
                fill="currentColor"
                d="M9 8h30a5 5 0 0 1 5 5v16a5 5 0 0 1-5 5H23l-9 7v-7h-5a5 5 0 0 1-5-5V13a5 5 0 0 1 5-5z"
            />
            <path
                v-else-if="cfg.shape === 'sparkle'"
                fill="currentColor"
                d="M24 3l5.5 15.5L45 24l-15.5 5.5L24 45l-5.5-15.5L3 24l15.5-5.5z"
            />
            <path
                v-else-if="cfg.shape === 'tally'"
                fill="none"
                stroke="currentColor"
                stroke-width="4"
                stroke-linecap="round"
                d="M13 11v26M21 11v26M29 11v26M37 11v26M9 26l32-9"
            />
            <polygon v-else-if="cfg.shape === 'diamond'" fill="currentColor" points="24,3 45,24 24,45 3,24" />
            <g v-else-if="cfg.shape === 'basketball'" fill="none" stroke="currentColor" stroke-width="3">
                <circle cx="24" cy="24" r="18" />
                <path d="M6 24h36M24 6v36M11 11l26 26M37 11L11 37" />
            </g>
        </svg>
    </span>
</template>

<style scoped>
.badge {
    display: grid;
    place-items: center;
    color: rgb(var(--ac));
}
.badge.token {
    width: 46px;
    height: 46px;
    border-radius: 13px;
    background: rgb(var(--color-surface-inset));
    border: 1.5px solid rgb(var(--ac) / 0.8);
    box-shadow:
        inset 0 0 0 1px rgb(var(--ac) / 0.15),
        0 0 14px rgb(var(--ac) / 0.3);
}
.badge.token svg {
    width: 23px;
    height: 23px;
}
.badge.bare svg {
    width: 18px;
    height: 18px;
}
</style>
