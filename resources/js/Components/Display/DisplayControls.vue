<script setup lang="ts">
/**
 * Floating presentation controls for the display boards. Sits bottom-right and
 * fades away during play (driven by `visible`), reappearing on any activity.
 * Nothing here is essential to the game — it's the "make the TV look right"
 * toolkit: go fullscreen, and nudge the overscan fit if a panel crops the edges.
 */
defineProps<{
    visible: boolean;
    isFullscreen: boolean;
    isFitInset: boolean;
}>();

defineEmits<{
    (e: 'toggle-fullscreen'): void;
    (e: 'cycle-fit'): void;
}>();
</script>

<template>
    <div
        class="fixed bottom-4 right-4 z-50 flex items-center gap-2 transition-opacity duration-300"
        :class="visible ? 'opacity-100' : 'pointer-events-none opacity-0'"
    >
        <!-- Overscan fit: cycles edge-to-edge → inset for TVs that crop -->
        <button
            type="button"
            class="flex h-11 w-11 items-center justify-center rounded-lg border border-border bg-surface-overlay/90 text-body shadow-lg backdrop-blur transition-colors hover:bg-surface-elevated"
            :class="{ 'border-primary text-primary': isFitInset }"
            title="Adjust screen fit (if the TV crops the edges)"
            aria-label="Adjust screen fit"
            @click="$emit('cycle-fit')"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7" />
            </svg>
        </button>

        <!-- Fullscreen toggle -->
        <button
            type="button"
            class="flex h-11 w-11 items-center justify-center rounded-lg border border-border bg-surface-overlay/90 text-body shadow-lg backdrop-blur transition-colors hover:bg-surface-elevated"
            :title="isFullscreen ? 'Exit fullscreen (F)' : 'Go fullscreen (F)'"
            :aria-label="isFullscreen ? 'Exit fullscreen' : 'Go fullscreen'"
            @click="$emit('toggle-fullscreen')"
        >
            <svg v-if="!isFullscreen" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3" />
            </svg>
            <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M8 3v3a2 2 0 0 1-2 2H3M21 8h-3a2 2 0 0 1-2-2V3M3 16h3a2 2 0 0 1 2 2v3M16 21v-3a2 2 0 0 1 2-2h3" />
            </svg>
        </button>
    </div>
</template>
