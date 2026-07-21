<script setup lang="ts">
/**
 * Keeler Games logo. `wordmark` = the cropped "KEELER GAMES" lockup (nav),
 * `full` = the complete trophy emblem (hero / auth). Placeholder art until the
 * final regenerated logo lands; swapping the files in /public/images updates
 * everywhere this component is used.
 */
withDefaults(defineProps<{ variant?: 'wordmark' | 'full' }>(), {
    variant: 'wordmark',
});
</script>

<template>
    <img
        :src="variant === 'full' ? '/images/keeler-logo.png' : '/images/keeler-wordmark.png'"
        alt="Keeler Games"
        draggable="false"
    />
</template>
