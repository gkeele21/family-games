<script setup lang="ts">
import KeelerLogo from '@/Components/KeelerLogo.vue';
import { Link } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';

const { theme } = useTheme();
</script>

<template>
    <div class="keeler-app" :class="`theme-${theme}`">
        <div
            class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-surface-inset px-4 py-10"
        >
            <!-- Aurora glow -->
            <div class="pointer-events-none absolute inset-0 overflow-hidden">
                <span class="absolute -left-16 -top-24 h-96 w-96 rounded-full bg-primary/25 blur-[110px]"></span>
                <span class="absolute -right-20 top-1/3 h-96 w-96 rounded-full bg-info/20 blur-[120px]"></span>
                <span class="absolute -bottom-24 left-1/3 h-80 w-80 rounded-full bg-warning/15 blur-[100px]"></span>
            </div>

            <Link href="/" class="relative z-10 mb-8">
                <KeelerLogo variant="full" class="h-auto w-64 drop-shadow-2xl" />
            </Link>

            <div
                class="relative z-10 w-full max-w-md rounded-2xl border border-border bg-surface-elevated/80 p-8 shadow-2xl backdrop-blur-sm"
            >
                <slot />
            </div>
        </div>
    </div>
</template>
