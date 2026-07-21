<template>
    <div>
        <FormLabel v-if="label" :html-for="inputId" :required="required">{{ label }}</FormLabel>

        <textarea
            :id="inputId"
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
            :placeholder="placeholder"
            :required="required"
            :disabled="disabled"
            :readonly="readonly"
            :rows="rows"
            :aria-describedby="descriptionId"
            :aria-invalid="!!error"
            :class="[
                'block w-full rounded-lg border transition-all resize-y',
                'focus:outline-none focus-glow',
                'bg-surface-inset text-body placeholder:text-muted',
                error
                    ? 'border-danger focus:border-transparent'
                    : 'border-border focus:border-transparent',
                disabled ? 'opacity-50 cursor-not-allowed' : '',
                sizes[size]
            ]"
        />

        <p v-if="error" :id="descriptionId" class="mt-1 text-sm text-danger">{{ error }}</p>
        <p v-else-if="hint" :id="descriptionId" class="mt-1 text-sm text-subtle">{{ hint }}</p>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import FormLabel from '@/Components/Form/FormLabel.vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    rows: { type: Number, default: 4 },
    size: { type: String, default: 'md' },
});

defineEmits(['update:modelValue']);

const sizes = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-3 py-2 text-base',
    lg: 'px-3 py-3 text-lg',
};

const inputId = computed(() => `textarea-${Math.random().toString(36).substr(2, 9)}`);
const descriptionId = computed(() => (props.error || props.hint) ? `${inputId.value}-desc` : undefined);
</script>
