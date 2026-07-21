<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        :class="[
            'block rounded-lg shadow transition-shadow',
            bgClass,
            hover && !disabled ? 'hover:shadow-lg cursor-pointer' : '',
            disabled ? 'opacity-50 cursor-not-allowed' : '',
            borderColors[borderColor],
            padding ? 'p-6' : ''
        ]"
    >
        <!-- Header -->
        <div v-if="$slots.header || title" :class="['border-b border-border', headerPadding ? 'px-6 py-4' : '', headerBgClass]">
            <slot name="header">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Icon v-if="icon" :name="icon" class="text-primary" />
                        <h3 class="text-lg font-semibold text-body">{{ title }}</h3>
                    </div>
                    <slot name="headerActions" />
                </div>
                <p v-if="subtitle" class="mt-1 text-sm text-subtle">{{ subtitle }}</p>
            </slot>
        </div>

        <!-- Body -->
        <div :class="bodyPadding && !padding ? 'p-6' : ''">
            <slot />
        </div>

        <!-- Footer -->
        <div v-if="$slots.footer" :class="['border-t border-border', footerPadding ? 'px-6 py-4' : '']">
            <slot name="footer" />
        </div>
    </component>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Base/Icon.vue';

const props = defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    icon: { type: String, default: '' },
    href: { type: String, default: '' },
    hover: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    borderColor: { type: String, default: 'none' },
    bgClass: { type: String, default: 'bg-surface border border-border' },
    padding: { type: Boolean, default: false },
    headerPadding: { type: Boolean, default: true },
    headerBgClass: { type: String, default: '' },
    bodyPadding: { type: Boolean, default: true },
    footerPadding: { type: Boolean, default: true },
});

const borderColors = {
    none: '',
    primary: 'border-l-4 border-l-primary',
    accent: 'border-l-4 border-l-warning',
    danger: 'border-l-4 border-l-danger',
    success: 'border-l-4 border-l-success',
    warning: 'border-l-4 border-l-warning',
    top: 'border-t-4 border-t-primary',
    'top-accent': 'border-t-4 border-t-warning',
};
</script>
