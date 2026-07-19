<template>
    <button
        :type="type"
        :class="[
            'rounded font-semibold transition whitespace-nowrap cursor-pointer disabled:opacity-70 disabled:cursor-not-allowed',
            variants[variant],
            sizes[size]
        ]"
        :disabled="disabled || loading"
    >
        <span v-if="loading" class="inline-flex items-center">
            <Icon name="spinner" class="animate-spin" :size="iconSize" />
            <span v-if="$slots.default" class="ml-2"><slot /></span>
        </span>
        <span v-else class="inline-flex items-center">
            <Icon v-if="icon" :name="icon" :size="iconSize" />
            <span v-if="$slots.default" :class="icon ? 'ml-2' : ''"><slot /></span>
        </span>
    </button>
</template>

<script setup>
import Icon from '@/Components/PropOff/Base/Icon.vue';

const props = defineProps({
    type: { type: String, default: 'button' },
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'sm' },
    icon: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});

const variants = {
    primary: 'bg-primary hover:bg-white text-white hover:text-primary border-2 border-transparent hover:border-primary',  // Theme color - main actions
    success: 'bg-success hover:bg-white text-white hover:text-success border-2 border-transparent hover:border-success',  // Green - positive/CTA actions
    secondary: 'bg-info hover:bg-white text-white hover:text-info border-2 border-transparent hover:border-info',  // Blue - secondary actions
    accent: 'bg-warning hover:bg-white text-white hover:text-warning border-2 border-transparent hover:border-warning',  // Orange - accent/highlight actions
    outline: 'bg-transparent hover:bg-primary/10 text-primary border-2 border-primary hover:border-primary',  // Outline with primary - cancel/back
    danger: 'bg-danger hover:bg-white text-white hover:text-danger border-2 border-transparent hover:border-danger',
    muted: 'bg-surface-inset hover:bg-surface-header text-body border-2 border-border hover:border-border-strong',  // Neutral/muted actions
    neutral: 'bg-black hover:bg-white text-white hover:text-black border-2 border-black hover:border-black',
    ghost: 'bg-transparent hover:bg-surface text-primary border-2 border-transparent',
    // Light variants for use on dark backgrounds
    'secondary-light': 'bg-white/10 hover:bg-white/20 text-white border border-white/30',
    'ghost-light': 'bg-transparent hover:bg-white/10 text-white',
    'outline-light': 'bg-transparent hover:bg-white/10 text-white border border-white/50',
};

const sizes = {
    xs: 'px-1.5 py-0.5 text-xs',
    sm: 'px-2 py-1 text-xs sm:text-sm',
    md: 'px-3 sm:px-4 py-1.5 sm:py-2 text-sm sm:text-base',
    lg: 'px-4 sm:px-6 py-2 sm:py-3 text-base sm:text-lg',
};

const iconSize = props.size === 'lg' ? 'md' : props.size === 'sm' ? 'xs' : 'sm';
</script>
