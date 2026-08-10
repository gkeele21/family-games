<script setup lang="ts">
import { onMounted, ref } from 'vue';

const model = defineModel<string>({ required: true });

const input = ref<HTMLInputElement | null>(null);

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) {
        input.value?.focus();
    }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <input
        class="rounded-lg border-border-strong bg-surface-inset text-body shadow-sm placeholder:text-muted focus:border-primary focus:ring-primary"
        v-model="model"
        ref="input"
    />
</template>
