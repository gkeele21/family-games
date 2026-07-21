<script setup>
import { computed } from 'vue';
import FormLabel from '@/Components/Form/FormLabel.vue';

const props = defineProps({
    modelValue: { type: [Number, String], default: '' },
    label: { type: String, default: '' },
    min: { type: Number, default: undefined },
    max: { type: Number, default: undefined },
    step: { type: Number, default: 1 },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    error: { type: [String, Boolean], default: '' },
    hint: { type: String, default: '' },
    placeholder: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const inputId = computed(() => `number-${Math.random().toString(36).substring(2, 11)}`);
const descriptionId = computed(() => (props.error || props.hint) ? `${inputId.value}-desc` : undefined);

const errorMessage = computed(() => {
    if (typeof props.error === 'string') return props.error;
    return '';
});

function handleInput(event) {
    const value = event.target.value === '' ? '' : Number(event.target.value);
    emit('update:modelValue', value);
}
</script>

<template>
    <div>
        <FormLabel v-if="label" :html-for="inputId" :required="required">{{ label }}</FormLabel>

        <input
            :id="inputId"
            type="number"
            :value="modelValue"
            @input="handleInput"
            :min="min"
            :max="max"
            :step="step"
            :required="required"
            :disabled="disabled"
            :placeholder="placeholder"
            :aria-describedby="descriptionId"
            :aria-invalid="!!error"
            :class="[
                'w-full px-4 py-2 border rounded-lg transition-all',
                'focus:outline-none focus-glow',
                'bg-surface-inset text-body placeholder:text-muted',
                error
                    ? 'border-danger focus:border-transparent'
                    : 'border-border focus:border-transparent',
                disabled ? 'opacity-50 cursor-not-allowed' : ''
            ]"
        />

        <p v-if="errorMessage" :id="descriptionId" class="mt-1 text-sm text-danger">{{ errorMessage }}</p>
        <p v-else-if="hint" :id="descriptionId" class="mt-1 text-sm text-muted">{{ hint }}</p>
    </div>
</template>
