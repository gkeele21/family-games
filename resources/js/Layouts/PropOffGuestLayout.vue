<script setup>
import { useTheme } from '@/composables/useTheme';
const { theme, bgMode } = useTheme();
import Logo from '@/Components/PropOff/Domain/Logo.vue';
</script>

<template>
    <div id="propoff-app" class="propoff-app" :class="[`theme-${theme}`, `bg-mode-${bgMode}`]">
    <div class="relative min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-surface-inset overflow-hidden">
        <!-- Aurora Background Blobs -->
        <div class="absolute top-20 left-10 w-72 h-72 rounded-full blur-[100px] bg-[#1a3490]/30"></div>
        <div class="absolute top-40 right-20 w-96 h-96 rounded-full blur-[120px] bg-warning/20"></div>
        <div class="absolute bottom-40 left-1/3 w-80 h-80 rounded-full blur-[100px] bg-success/25"></div>
        <div class="absolute bottom-20 right-1/4 w-64 h-64 rounded-full blur-[80px] bg-danger/15"></div>

        <!-- Content -->
        <div class="relative z-10">
            <Logo size="lg" link-to="/" />
        </div>

        <div class="relative z-10 w-full sm:max-w-md mt-6 px-6 py-6 bg-surface-elevated border border-border backdrop-blur-sm shadow-2xl overflow-hidden sm:rounded-xl">
            <slot />
        </div>
    </div>
    </div>
</template>

<style scoped>
/* Input focus glow - applies to all inputs in guest layout */
:deep(input:focus) {
    outline: none;
    box-shadow: 0 0 0 1px rgb(var(--color-text)), 0 0 0 3px #1a3490, 0 0 15px rgba(26, 52, 144, 0.3);
    border-color: transparent;
}

/* Button hover glow for primary buttons */
:deep(.btn-primary:hover:not(:disabled)),
:deep(button[type="submit"]:hover:not(:disabled)) {
    box-shadow: 0 0 0 1px rgb(var(--color-text)), 0 0 0 3px rgb(var(--color-success)), 0 0 20px rgba(87, 208, 37, 0.4);
}
</style>