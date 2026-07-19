<template>
    <div class="contents">
        <span class="text-sm font-semibold text-gray-dark whitespace-nowrap pr-2">{{ label }}</span>
        <span class="text-sm" :class="valueClass"><slot>{{ value }}</slot></span>
    </div>
</template>

<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: '' },
    valueClass: { type: String, default: '' },
});
</script>
