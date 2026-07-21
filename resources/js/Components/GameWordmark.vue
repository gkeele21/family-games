<script setup lang="ts">
/**
 * Two-tone game wordmark in the PropOff logo style: a sharp diamond + a
 * split wordmark (first half in body color, second half in the game's accent).
 * Used on the landing cards so each game reads as its own sub-brand.
 */
withDefaults(
    defineProps<{
        first: string;
        second: string;
        /** any CSS color for the accent half + diamond, e.g. rgb(var(--color-info)) */
        color?: string;
        size?: 'md' | 'lg';
    }>(),
    { color: 'rgb(var(--color-primary))', size: 'md' },
);
</script>

<template>
    <span class="inline-flex items-center gap-3" :style="{ '--gw': color }">
        <svg
            class="dia shrink-0"
            :class="size === 'lg' ? 'h-8 w-[27px]' : 'h-7 w-[23px]'"
            viewBox="0 0 40 48"
        >
            <polygon points="20,2 38,24 20,46 2,24" />
        </svg>
        <span
            class="font-extrabold leading-none tracking-tight"
            :class="size === 'lg' ? 'text-[40px]' : 'text-[28px]'"
        >
            <span class="text-body">{{ first }}</span><span class="accent">{{ second }}</span>
        </span>
    </span>
</template>

<style scoped>
.dia {
    filter: drop-shadow(0 0 10px color-mix(in srgb, var(--gw) 45%, transparent));
}
.dia polygon {
    fill: var(--gw);
}
.accent {
    color: var(--gw);
}
</style>
