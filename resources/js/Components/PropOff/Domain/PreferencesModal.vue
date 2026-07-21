<template>
    <Modal :show="show" @close="$emit('close')" max-width="md">
        <div class="p-6">
            <h2 class="text-lg font-semibold text-body mb-6">Preferences</h2>

            <!-- Theme Color -->
            <section>
                <h3 class="text-sm font-medium text-body">Accent Color</h3>
                <p class="mt-1 text-sm text-muted">Choose your preferred accent color.</p>

                <div class="mt-4 flex gap-3">
                    <button
                        v-for="option in themeOptions"
                        :key="option.value"
                        type="button"
                        @click="setTheme(option.value)"
                        class="flex flex-col items-center gap-2 p-3 rounded-lg border-2 transition-all"
                        :class="[
                            theme === option.value
                                ? 'border-primary bg-surface-elevated'
                                : 'border-border hover:border-border-strong hover:bg-surface-overlay',
                        ]"
                    >
                        <span
                            class="w-8 h-8 rounded-full"
                            :style="{ backgroundColor: option.color }"
                        ></span>
                        <span class="text-xs font-medium text-body">{{ option.label }}</span>
                    </button>
                </div>
            </section>

            <!-- Background Mode -->
            <section class="mt-6">
                <h3 class="text-sm font-medium text-body">Background</h3>
                <p class="mt-1 text-sm text-muted">Choose your preferred page background.</p>

                <div class="mt-4 flex gap-3">
                    <button
                        v-for="option in bgModeOptions"
                        :key="option.value"
                        type="button"
                        @click="setBgMode(option.value)"
                        class="flex flex-col items-center gap-2 p-3 rounded-lg border-2 transition-all"
                        :class="[
                            bgMode === option.value
                                ? 'border-primary bg-surface-elevated'
                                : 'border-border hover:border-border-strong hover:bg-surface-overlay',
                        ]"
                    >
                        <span
                            class="w-8 h-8 rounded-lg border border-border"
                            :style="{ backgroundColor: option.color }"
                        ></span>
                        <span class="text-xs font-medium text-body">{{ option.label }}</span>
                    </button>
                </div>
            </section>

            <!-- Close button -->
            <div class="mt-8 flex justify-end">
                <Button variant="primary" @click="$emit('close')">Done</Button>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import Modal from '@/Components/Base/Modal.vue';
import Button from '@/Components/Base/Button.vue';
import { useTheme } from '@/composables/useTheme';

defineProps({
    show: { type: Boolean, default: false },
});

defineEmits(['close']);

const { theme, setTheme, bgMode, setBgMode } = useTheme();

const themeOptions = [
    { value: 'green', label: 'Green', color: '#57d025' },
    { value: 'blue', label: 'Blue', color: '#3b82f6' },
    { value: 'orange', label: 'Orange', color: '#f47612' },
];

const bgModeOptions = [
    { value: 'slate', label: 'Slate', color: '#404040' },
    { value: 'cream', label: 'Cream', color: '#f5f3ef' },
];
</script>
