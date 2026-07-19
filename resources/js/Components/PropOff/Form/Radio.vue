<template>
    <label
        :for="inputId"
        :class="[
            'flex items-start gap-3 p-4 border rounded-lg transition-all focus-within-glow',
            disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
            modelValue === value
                ? 'border-primary bg-primary/10 checked-glow'
                : 'border-border bg-surface-inset hover:border-border-strong hover:bg-surface-overlay'
        ]"
    >
        <input
            :id="inputId"
            type="radio"
            :name="name"
            :value="value"
            :checked="modelValue === value"
            @change="$emit('update:modelValue', value)"
            :required="required"
            :disabled="disabled"
            class="sr-only"
        />
        <!-- Custom Radio Circle -->
        <div
            :class="[
                'w-5 h-5 rounded-full border-2 flex-shrink-0 mt-0.5 flex items-center justify-center transition-colors',
                modelValue === value
                    ? 'border-primary bg-primary'
                    : 'border-white/80 bg-transparent'
            ]"
        >
            <div
                v-if="modelValue === value"
                class="w-2 h-2 rounded-full bg-white"
            ></div>
        </div>
        <div class="flex-1">
            <span class="font-semibold text-body">
                {{ label }}
                <span v-if="required" class="text-danger">*</span>
            </span>
            <p v-if="description" class="text-sm text-muted mt-1">{{ description }}</p>
        </div>
    </label>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, Boolean], default: '' },
    value: { type: [String, Number, Boolean], required: true },
    name: { type: String, required: true },
    label: { type: String, default: '' },
    description: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const inputId = computed(() => `radio-${props.name}-${Math.random().toString(36).substr(2, 9)}`);
</script>
