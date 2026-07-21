<template>
    <div>
        <FormLabel v-if="label" :html-for="inputId" :required="required" :variant="labelVariant">{{ label }}</FormLabel>

        <div class="relative">
            <div v-if="iconLeft && !multiline" class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <Icon :name="iconLeft" size="sm" class="text-muted" />
            </div>

            <textarea
                v-if="multiline"
                :id="inputId"
                :value="modelValue"
                @input="$emit('update:modelValue', $event.target.value)"
                :placeholder="placeholder"
                :required="required"
                :disabled="disabled"
                :readonly="readonly"
                :rows="rows"
                :class="[
                    'block w-full rounded-lg border transition-all resize-y',
                    'focus:outline-none focus-glow',
                    'bg-surface-inset text-body placeholder:text-muted',
                    error
                        ? 'border-danger focus:border-transparent'
                        : 'border-border focus:border-transparent',
                    disabled ? 'opacity-50 cursor-not-allowed' : '',
                    'px-4',
                    sizes[size]
                ]"
            />

            <input
                v-else
                :id="inputId"
                :type="type"
                :value="modelValue"
                @input="$emit('update:modelValue', $event.target.value)"
                :placeholder="placeholder"
                :required="required"
                :disabled="disabled"
                :readonly="readonly"
                :class="[
                    'block w-full rounded-lg border transition-all',
                    'focus:outline-none focus-glow',
                    'bg-surface-inset text-body placeholder:text-muted',
                    error
                        ? 'border-danger focus:border-transparent'
                        : 'border-border focus:border-transparent',
                    disabled ? 'opacity-50 cursor-not-allowed' : '',
                    iconLeft ? 'pl-10' : 'px-4',
                    iconRight ? 'pr-10' : '',
                    sizes[size]
                ]"
            />

            <div v-if="iconRight && !multiline" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                <Icon :name="iconRight" size="sm" class="text-muted" />
            </div>
        </div>

        <p v-if="error" class="mt-1 text-sm text-danger">{{ error }}</p>
        <p v-else-if="hint" class="mt-1 text-sm text-subtle">{{ hint }}</p>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import Icon from '@/Components/Base/Icon.vue';
import FormLabel from '@/Components/Form/FormLabel.vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    label: { type: String, default: '' },
    labelVariant: { type: String, default: 'default' },
    placeholder: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    size: { type: String, default: 'md' },
    iconLeft: { type: String, default: '' },
    iconRight: { type: String, default: '' },
    multiline: { type: Boolean, default: false },
    rows: { type: Number, default: 3 },
});

defineEmits(['update:modelValue']);

const sizes = {
    sm: 'py-1.5 text-sm',
    md: 'py-2 text-base',
    lg: 'py-3 text-lg',
};

const inputId = computed(() => `textfield-${Math.random().toString(36).substring(2, 11)}`);
</script>
