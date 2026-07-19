<template>
    <div>
        <div class="flex items-start gap-2">
            <input
                :id="inputId"
                type="checkbox"
                :checked="modelValue"
                @change="$emit('update:modelValue', $event.target.checked)"
                :required="required"
                :disabled="disabled"
                :aria-describedby="error ? `${inputId}-error` : undefined"
                :aria-invalid="!!error"
                :class="[
                    'rounded border-white/80 bg-surface-inset text-primary mt-0.5 transition-all',
                    'focus-glow-sm focus:ring-primary focus:ring-offset-0 focus:ring-offset-surface',
                    modelValue ? 'checked-glow' : '',
                    disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
                    error ? 'border-danger' : ''
                ]"
            />
            <label
                v-if="label || $slots.label"
                :for="inputId"
                :class="[
                    'select-none',
                    disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
                    labelVariants[labelVariant]
                ]"
            >
                <slot name="label">
                    {{ label }}
                    <span v-if="required" class="text-danger">*</span>
                </slot>
                <p v-if="description" class="text-sm text-subtle mt-0.5">{{ description }}</p>
            </label>
        </div>
        <p v-if="error" :id="`${inputId}-error`" class="mt-1 text-sm text-danger">{{ error }}</p>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    label: { type: String, default: '' },
    labelVariant: { type: String, default: 'default' },
    description: { type: String, default: '' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const labelVariants = {
    default: 'text-body',
    inverse: 'text-white',
    muted: 'text-muted',
};

const inputId = computed(() => `checkbox-${Math.random().toString(36).substr(2, 9)}`);
</script>
