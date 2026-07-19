<template>
    <span class="inline-flex"><i :class="iconClass" aria-hidden="true" /></span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    variant: { type: String, default: 'solid' },
    size: { type: String, default: 'md' },
});

const sizes = {
    xs: 'text-xs',
    sm: 'text-sm',
    md: 'text-base',
    lg: 'text-lg',
    xl: 'text-xl',
    '2x': 'text-2xl',
    '3x': 'text-3xl',
    '4x': 'text-4xl',
    '5x': 'text-5xl',
};

const iconClass = computed(() => {
    return `fa-${props.variant} fa-${props.name} fa-fw ${sizes[props.size] || sizes.md}`;
});
</script>
