<template>
    <div :class="['flex items-center', inline ? 'gap-3' : 'flex-col items-start gap-1']">
        <FormLabel v-if="label && !inline" :required="required" no-margin>{{ label }}</FormLabel>

        <button
            type="button"
            role="switch"
            :aria-checked="modelValue"
            :aria-label="label"
            @click="toggle"
            :disabled="disabled"
            :class="[
                'relative inline-flex shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-all duration-200 ease-in-out',
                'focus:outline-none focus-glow-sm',
                modelValue ? 'bg-primary checked-glow' : 'bg-muted',
                disabled ? 'opacity-50 cursor-not-allowed' : '',
                sizes[size].button
            ]"
        >
            <span
                :class="[
                    'pointer-events-none inline-block transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                    modelValue ? sizes[size].translateOn : 'translate-x-0',
                    sizes[size].dot
                ]"
            />
        </button>

        <label v-if="label && inline" :class="['select-none', disabled ? 'opacity-50' : '', 'text-gray-dark']">
            {{ label }}
            <span v-if="required" class="text-danger">*</span>
        </label>

        <p v-if="error" class="text-sm text-danger">{{ error }}</p>
    </div>
</template>

<script setup>
import FormLabel from '@/Components/Form/FormLabel.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    label: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: '' },
    size: { type: String, default: 'md' },
    inline: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const sizes = {
    sm: {
        button: 'h-5 w-9',
        dot: 'h-4 w-4',
        translateOn: 'translate-x-4',
    },
    md: {
        button: 'h-6 w-11',
        dot: 'h-5 w-5',
        translateOn: 'translate-x-5',
    },
    lg: {
        button: 'h-7 w-14',
        dot: 'h-6 w-6',
        translateOn: 'translate-x-7',
    },
};

function toggle() {
    if (!props.disabled) {
        emit('update:modelValue', !props.modelValue);
    }
}
</script>
