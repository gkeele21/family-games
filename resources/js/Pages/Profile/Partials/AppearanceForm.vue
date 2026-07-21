<script setup lang="ts">
import { useTheme } from '@/composables/useTheme';

const { theme, setTheme } = useTheme();

const themeOptions = [
    { value: 'green', label: 'Green', color: '#57d025' },
    { value: 'blue', label: 'Blue', color: '#3b82f6' },
    { value: 'orange', label: 'Orange', color: '#f47612' },
];
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-semibold text-body">Appearance</h2>
            <p class="mt-1 text-sm text-muted">
                Choose your accent color. It's saved to this device and applied everywhere across Keeler Games.
            </p>
        </header>

        <div class="mt-6">
            <div class="flex flex-wrap gap-3">
                <button
                    v-for="option in themeOptions"
                    :key="option.value"
                    type="button"
                    @click="setTheme(option.value)"
                    class="flex flex-col items-center gap-2 rounded-lg border-2 p-3 transition-all"
                    :class="
                        theme === option.value
                            ? 'border-primary bg-surface-elevated'
                            : 'border-border hover:border-border-strong hover:bg-surface-overlay'
                    "
                >
                    <span class="h-8 w-8 rounded-full" :style="{ backgroundColor: option.color }"></span>
                    <span class="text-xs font-medium text-body">{{ option.label }}</span>
                </button>
            </div>
        </div>
    </section>
</template>
