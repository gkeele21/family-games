<script setup lang="ts">
withDefaults(
    defineProps<{
        type?: 'button' | 'submit' | 'reset';
    }>(),
    {
        type: 'button',
    },
);
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center rounded-lg border border-border-strong bg-surface-overlay px-4 py-2 text-sm font-semibold text-body transition duration-150 ease-in-out hover:bg-surface-elevated focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-surface disabled:cursor-not-allowed disabled:opacity-40"
    >
        <slot />
    </button>
</template>
