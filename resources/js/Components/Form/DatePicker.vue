<template>
    <div>
        <FormLabel v-if="label" :html-for="inputId" :required="required">{{ label }}</FormLabel>

        <VueDatePicker
            :id="inputId"
            v-model="internalValue"
            :enable-time-picker="showTimePicker"
            :time-picker="timeOnly"
            :month-picker="monthOnly"
            :time-config="{ is24: false }"
            :format="displayFormat"
            :model-type="modelType"
            :min-date="min"
            :max-date="max"
            :disabled="disabled"
            :readonly="readonly"
            :required="required"
            :placeholder="placeholder"
            :auto-apply="shouldAutoApply"
            :close-on-auto-apply="true"
            :text-input="textInput"
            :aria-describedby="descriptionId"
            :class="[
                error ? 'dp-error' : '',
                disabled ? 'dp-disabled' : '',
                `dp-size-${size}`
            ]"
        />

        <p v-if="error" :id="descriptionId" class="mt-1 text-sm text-danger">{{ error }}</p>
        <p v-else-if="hint" :id="descriptionId" class="mt-1 text-sm text-subtle">{{ hint }}</p>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { VueDatePicker } from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import FormLabel from '@/Components/Form/FormLabel.vue';

const props = defineProps({
    modelValue: { type: [String, Date, Object], default: '' },
    type: { type: String, default: 'date' }, // 'date', 'datetime', 'time', 'month'
    enableTime: { type: Boolean, default: false }, // Shorthand for type="datetime"
    label: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    format: { type: String, default: '' },
    min: { type: [String, Date], default: null },
    max: { type: [String, Date], default: null },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    autoApply: { type: Boolean, default: true },
    textInput: { type: Boolean, default: true },
    size: { type: String, default: 'md' },
});

const emit = defineEmits(['update:modelValue']);

// Normalize type (handle datetime-local as alias for datetime, or enableTime prop)
const normalizedType = computed(() => {
    if (props.enableTime) return 'datetime';
    if (props.type === 'datetime-local') return 'datetime';
    return props.type;
});

// Computed props based on type
const showTimePicker = computed(() => normalizedType.value === 'datetime');
const timeOnly = computed(() => normalizedType.value === 'time');
const monthOnly = computed(() => normalizedType.value === 'month');

// For datetime, don't auto-apply so user can select time; for date-only, auto-apply is fine
const shouldAutoApply = computed(() => {
    if (normalizedType.value === 'datetime') return false;
    return props.autoApply;
});

// Display format based on type
const displayFormat = computed(() => {
    if (props.format) return props.format;
    switch (normalizedType.value) {
        case 'datetime': return 'MM/dd/yyyy hh:mm aa';
        case 'time': return 'hh:mm aa';
        case 'month': return 'MMMM yyyy';
        default: return 'MM/dd/yyyy';
    }
});

// Model type for v-model output format (string format for backwards compatibility)
const modelType = computed(() => {
    switch (normalizedType.value) {
        case 'datetime': return "yyyy-MM-dd'T'HH:mm";  // ISO format with T separator
        case 'time': return 'HH:mm';
        case 'month': return 'yyyy-MM';
        default: return 'yyyy-MM-dd';
    }
});

// Internal value handling
const internalValue = ref(props.modelValue || null);

watch(() => props.modelValue, (newVal) => {
    internalValue.value = newVal || null;
});

watch(internalValue, (newVal) => {
    emit('update:modelValue', newVal);
});

const inputId = computed(() => `date-${Math.random().toString(36).substr(2, 9)}`);
const descriptionId = computed(() => (props.error || props.hint) ? `${inputId.value}-desc` : undefined);
</script>

<style>
/* Custom styling to match our design system */
.dp__input {
    @apply rounded border border-border bg-surface-inset text-body pr-3;
    padding-left: 2.5rem; /* Make room for the calendar icon */
    transition: all 0.15s ease;
}

.dp__input:focus {
    outline: none;
    border-color: transparent;
    box-shadow:
        0 0 0 1px rgb(var(--color-text)),
        0 0 0 3px rgb(var(--color-primary)),
        0 0 15px rgb(var(--color-primary) / 0.3);
}

/* Size variants - match TextField sizing */
.dp-size-sm .dp__input {
    @apply py-1.5 text-sm;
}

.dp-size-md .dp__input {
    @apply py-2 text-base;
}

.dp-size-lg .dp__input {
    @apply py-3 text-lg;
}

.dp-error .dp__input {
    @apply border-danger focus:border-transparent;
}

.dp-disabled .dp__input {
    @apply opacity-50 cursor-not-allowed;
}

/* Dark mode theme for date picker popup */
.dp__theme_light {
    --dp-primary-color: rgb(var(--color-primary));
    --dp-primary-text-color: #ffffff;
    --dp-secondary-color: rgb(var(--color-border));
    --dp-border-color: rgb(var(--color-border));
    --dp-background-color: rgb(var(--color-surface-elevated));
    --dp-text-color: rgb(var(--color-text));
    --dp-hover-color: rgb(var(--color-surface-overlay));
    --dp-hover-text-color: rgb(var(--color-text));
    --dp-disabled-color: rgb(var(--color-text-subtle));
}
</style>
