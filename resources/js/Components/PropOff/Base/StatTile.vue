<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="bg-surface-inset border border-border border-t-4 rounded-lg p-4 sm:p-5 text-center block"
        :class="[
            borderClasses[color],
            href ? 'hover:bg-surface-overlay hover:border-border-strong transition-colors cursor-pointer' : ''
        ]"
    >
        <div class="text-2xl sm:text-3xl font-bold mb-1" :class="textClasses[color]">
            {{ value }}
        </div>
        <div class="text-xs text-muted uppercase tracking-wider">
            {{ label }}
        </div>
    </component>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    value: {
        type: [String, Number],
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    color: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'success', 'warning', 'danger', 'info', 'neutral'].includes(value),
    },
    href: {
        type: String,
        default: null,
    },
});

// Explicit class mappings for Tailwind purging
const borderClasses = {
    primary: 'border-t-primary',
    success: 'border-t-success',
    warning: 'border-t-warning',
    danger: 'border-t-danger',
    info: 'border-t-info',
    neutral: 'border-t-white',
};

const textClasses = {
    primary: 'text-primary',
    success: 'text-success',
    warning: 'text-warning',
    danger: 'text-danger',
    info: 'text-info',
    neutral: 'text-body',
};
</script>
