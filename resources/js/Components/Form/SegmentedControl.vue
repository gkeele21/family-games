<template>
    <div>
        <FormLabel v-if="label" :id="labelId" :required="required">{{ label }}</FormLabel>

        <div
            class="inline-flex"
            role="radiogroup"
            :aria-labelledby="label ? labelId : undefined"
        >
            <button
                v-for="(option, index) in visibleOptions"
                :key="getOptionValue(option)"
                type="button"
                role="radio"
                :aria-checked="isSelected(option)"
                @click="selectOption(option)"
                :disabled="disabled || option.disabled"
                :class="[
                    'relative inline-flex items-center justify-center transition-colors',
                    'focus:outline-none focus:ring-2 focus:ring-primary focus:z-10',
                    // Size styles
                    sizes[size],
                    // Border radius based on position
                    index === 0 ? 'rounded-l' : '',
                    index === visibleOptions.length - 1 ? 'rounded-r' : '',
                    // Border styles
                    'border',
                    index !== 0 ? '-ml-px' : '',
                    // State styles - use option's selectedStyle if provided, otherwise use variant
                    selectedValue == getOptionValue(option)
                        ? (option.selectedStyle || variantStyles[variant].selected)
                        : variantStyles[variant].unselected,
                    // Disabled state
                    (disabled || option.disabled)
                        ? 'opacity-50 cursor-not-allowed'
                        : 'cursor-pointer'
                ]"
            >
                <span v-if="showCheck && isSelected(option)" class="mr-1">
                    <i class="fa-solid fa-check fa-xs"></i>
                </span>
                <i v-if="option.icon" :class="`fa-solid fa-${option.icon} text-sm ${option.text ? 'mr-1' : ''}`"></i>
                <span>{{ getOptionLabel(option) }}</span>
                <Tooltip
                    v-if="option.tooltip"
                    :content="option.tooltip"
                    icon-class="text-current opacity-70 hover:opacity-100 ml-1"
                    icon-size="xs"
                />
            </button>
        </div>

        <p v-if="error" class="mt-1 text-sm text-danger">{{ error }}</p>
        <p v-else-if="hint" class="mt-1 text-sm text-subtle">{{ hint }}</p>
    </div>
</template>

<script setup>
import { computed, toRef } from 'vue';
import FormLabel from '@/Components/Form/FormLabel.vue';
import Tooltip from '@/Components/Feedback/Tooltip.vue';

const props = defineProps({
    modelValue: { type: [String, Number, Boolean], default: null },
    options: { type: Array, required: true },
    label: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    size: { type: String, default: 'md' },
    variant: { type: String, default: 'primary' },
    showCheck: { type: Boolean, default: false },
    // Flexible option format
    valueKey: { type: String, default: 'value' },
    labelKey: { type: String, default: 'label' },
});

const emit = defineEmits(['update:modelValue', 'change']);

// Create reactive ref from modelValue prop for better reactivity tracking
const selectedValue = toRef(props, 'modelValue');

const sizes = {
    sm: 'px-3 py-1 text-sm',
    md: 'px-4 py-2 text-sm',
    lg: 'px-5 py-2.5 text-base',
};

const variantStyles = {
    primary: {
        selected: 'bg-primary text-white border-primary',
        unselected: 'bg-white text-body border-border hover:bg-surface',
    },
    secondary: {
        selected: 'bg-surface text-body border-border',
        unselected: 'bg-white text-body border-border hover:bg-surface',
    },
    accent: {
        selected: 'bg-warning text-body border-warning',
        unselected: 'bg-white text-body border-border hover:bg-surface',
    },
};

const labelId = computed(() => `segmented-label-${Math.random().toString(36).substr(2, 9)}`);

// Filter out hidden options
const visibleOptions = computed(() => {
    return props.options.filter(option => !option.hide && !option.hidden);
});

// Support both object options and primitive options
function getOptionValue(option) {
    if (typeof option === 'object' && option !== null) {
        return option[props.valueKey] ?? option.id ?? option.value;
    }
    return option;
}

function getOptionLabel(option) {
    if (typeof option === 'object' && option !== null) {
        return option[props.labelKey] ?? option.text ?? option.name ?? option.label;
    }
    return option;
}

function isSelected(option) {
    // Use loose equality to handle string/number type mismatches
    return selectedValue.value == getOptionValue(option);
}

function selectOption(option) {
    if (props.disabled || option.disabled) return;
    const value = getOptionValue(option);
    emit('update:modelValue', value);
    emit('change', value);
}
</script>
