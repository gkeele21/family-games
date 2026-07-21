<template>
    <span
        :class="[
            'inline-flex items-center font-semibold',
            variants[variant],
            sizes[size],
            roundedClass
        ]"
    >
        <Icon v-if="icon" :name="icon" :size="iconSize" class="mr-1" />
        <slot />
    </span>
</template>

<script setup>
import { computed } from 'vue';
import Icon from '@/Components/Base/Icon.vue';

const props = defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    icon: { type: String, default: '' },
    rounded: { type: String, default: '' },
});

const roundedClass = computed(() => {
    if (!props.rounded) return 'rounded-full';  // Pill shape by default (matches mockup)
    if (props.rounded === 'none') return '';
    return `rounded-${props.rounded}`;
});

const variants = {
    // Solid variants
    primary: 'bg-primary text-white',
    secondary: 'bg-secondary text-white',
    accent: 'bg-warning text-white',
    success: 'bg-success text-white',
    danger: 'bg-danger text-white',
    warning: 'bg-warning text-body',
    info: 'bg-info text-white',
    outline: 'bg-transparent border border-primary text-primary',
    dark: 'bg-black text-white',
    light: 'bg-white/20 text-white border border-white/30',
    'outline-light': 'bg-transparent border border-white/50 text-white',
    // Soft variants - 15% opacity bg, solid text (matches dark mode mockup)
    'primary-soft': 'bg-primary/15 text-primary',
    'success-soft': 'bg-success/15 text-success',
    'warning-soft': 'bg-warning/15 text-warning',
    'danger-soft': 'bg-danger/15 text-danger',
    'info-soft': 'bg-info/15 text-info',
    // Default/muted for draft, inactive states
    'default': 'bg-surface-elevated text-muted',
};

const sizes = {
    sm: 'px-1.5 py-0.5 text-xs',
    md: 'px-2 py-0.5 text-sm',
    lg: 'px-3 py-1 text-base',
};

const iconSize = computed(() => props.size === 'lg' ? 'sm' : 'xs');
</script>
