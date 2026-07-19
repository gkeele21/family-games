<template>
    <label
        :for="htmlFor"
        :id="id"
        :class="['block text-sm font-semibold', variantClasses[variant], spacingClass]"
    >
        <slot>{{ label }}</slot>
        <span v-if="required" class="text-danger ml-0.5">*</span>
    </label>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, default: '' },
    htmlFor: { type: String, default: '' },
    id: { type: String, default: '' },
    required: { type: Boolean, default: false },
    noMargin: { type: Boolean, default: false },
    variant: { type: String, default: 'default' },
});

const variantClasses = {
    default: 'text-body',
    inverse: 'text-white',
    muted: 'text-muted',
};

const spacingClass = computed(() => props.noMargin ? '' : 'mb-1');
</script>
