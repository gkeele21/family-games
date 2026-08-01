<script setup>
import { computed } from 'vue';
import FormLabel from '@/Components/Form/FormLabel.vue';

// Segmented −/[value]/+ number control. Ported from the OraTek design system and
// recolored to the Keeler palette tokens. `showControls` can hide the buttons to
// fall back to a plain number field.
const props = defineProps({
    modelValue: { type: Number, default: 0 },
    label: { type: String, default: '' },
    min: { type: Number, default: undefined },
    max: { type: Number, default: undefined },
    step: { type: Number, default: 1 },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    showControls: { type: Boolean, default: true },
    error: { type: [String, Boolean], default: '' },
    hint: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    size: { type: String, default: 'md' }, // sm, md, lg
    inputClass: { type: String, default: '' }, // override the value field's width (e.g. 'w-24' for large numbers)
});

const emit = defineEmits(['update:modelValue']);

const sizes = {
    sm: { input: 'text-sm', width: 'w-12', button: 'px-2.5 py-1' },
    md: { input: 'text-base', width: 'w-16', button: 'px-3 py-2' },
    lg: { input: 'text-lg', width: 'w-20', button: 'px-4 py-3' },
};

const inputId = computed(() => `number-${Math.random().toString(36).substr(2, 9)}`);
const descriptionId = computed(() => (props.error || props.hint) ? `${inputId.value}-desc` : undefined);
const errorMessage = computed(() => (typeof props.error === 'string' ? props.error : ''));

function handleInput(event) {
    emit('update:modelValue', Number(event.target.value));
}

// Clamp to [min, max] when the field loses focus. The −/+ buttons already enforce
// the bounds, but a typed value can go out of range — snap it back so the shown
// value is always valid.
function handleBlur(event) {
    let value = Number(event.target.value);
    if (Number.isNaN(value)) value = props.min ?? 0;
    if (props.min !== undefined && value < props.min) value = props.min;
    if (props.max !== undefined && value > props.max) value = props.max;
    if (value !== props.modelValue) emit('update:modelValue', value);
}

function increment() {
    if (props.max !== undefined && props.modelValue >= props.max) return;
    emit('update:modelValue', props.modelValue + props.step);
}

function decrement() {
    if (props.min !== undefined && props.modelValue <= props.min) return;
    emit('update:modelValue', props.modelValue - props.step);
}
</script>

<template>
    <div>
        <FormLabel v-if="label" :html-for="inputId" :required="required">{{ label }}</FormLabel>

        <div
            :class="[
                'inline-flex items-stretch overflow-hidden rounded-lg',
                showControls ? 'border' : '',
                error ? 'border-danger' : 'border-border',
            ]"
        >
            <!-- Decrement -->
            <button
                v-if="showControls"
                type="button"
                aria-label="Decrease value"
                :disabled="disabled || (min !== undefined && modelValue <= min)"
                :class="[
                    'flex items-center justify-center text-lg font-bold transition-colors',
                    'focus:outline-none focus:ring-1 focus:ring-inset focus:ring-primary',
                    disabled ? 'cursor-not-allowed bg-surface-inset text-muted' : 'bg-surface-overlay text-body hover:bg-surface-elevated',
                    sizes[size].button,
                ]"
                @click="decrement"
            >
                &minus;
            </button>

            <!-- Value -->
            <input
                :id="inputId"
                type="number"
                :value="modelValue"
                :min="min"
                :max="max"
                :step="step"
                :required="required"
                :disabled="disabled"
                :placeholder="placeholder"
                :aria-describedby="descriptionId"
                :aria-invalid="!!error"
                :class="[
                    'text-center font-semibold text-body transition-colors placeholder:text-muted',
                    'focus:outline-none focus:ring-1 focus:ring-inset',
                    '[appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none',
                    error ? 'focus:ring-danger' : 'focus:ring-primary',
                    disabled ? 'cursor-not-allowed bg-surface-inset text-muted' : 'bg-surface-inset',
                    showControls ? 'border-x border-border' : 'rounded-lg border border-border',
                    sizes[size].input,
                    inputClass || sizes[size].width,
                ]"
                @input="handleInput"
                @blur="handleBlur"
            />

            <!-- Increment -->
            <button
                v-if="showControls"
                type="button"
                aria-label="Increase value"
                :disabled="disabled || (max !== undefined && modelValue >= max)"
                :class="[
                    'flex items-center justify-center text-lg font-bold transition-colors',
                    'focus:outline-none focus:ring-1 focus:ring-inset focus:ring-primary',
                    disabled ? 'cursor-not-allowed bg-surface-inset text-muted' : 'bg-surface-overlay text-body hover:bg-surface-elevated',
                    sizes[size].button,
                ]"
                @click="increment"
            >
                +
            </button>
        </div>

        <p v-if="errorMessage" :id="descriptionId" class="mt-1 text-sm text-danger">{{ errorMessage }}</p>
        <p v-else-if="hint" :id="descriptionId" class="mt-1 text-sm text-subtle">{{ hint }}</p>
    </div>
</template>
