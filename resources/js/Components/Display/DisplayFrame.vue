<script setup lang="ts">
import { computed } from 'vue';
import { useDisplayPresentation } from '@/composables/useDisplayPresentation';
import DisplayControls from './DisplayControls.vue';

/**
 * Full-viewport wrapper for the TV/projector display boards. Owns the screen
 * (fixed, edge-to-edge, no scroll), keeps the device awake, hides the cursor +
 * controls during play, and applies the live overscan-fit scale so the board
 * sits inside whatever the TV actually renders. Drop any display board into its
 * default slot — the boards keep using 100vh internally and get scaled as one.
 */
const { isFullscreen, toggleFullscreen, uiActive, fitScale, isFitInset, cycleFit } =
    useDisplayPresentation();

const stageStyle = computed(() => ({
    transform: `scale(${fitScale.value})`,
}));
</script>

<template>
    <div class="display-frame bg-bg" :class="{ 'cursor-none': !uiActive }">
        <div class="display-frame__stage" :style="stageStyle">
            <slot />
        </div>

        <DisplayControls
            :visible="uiActive"
            :is-fullscreen="isFullscreen"
            :is-fit-inset="isFitInset"
            @toggle-fullscreen="toggleFullscreen"
            @cycle-fit="cycleFit"
        />
    </div>
</template>

<style scoped>
.display-frame {
    position: fixed;
    inset: 0;
    overflow: hidden;
}

.display-frame__stage {
    width: 100%;
    height: 100%;
    transform-origin: center center;
    /* Smooth the step when the fit is nudged; instant enough to feel responsive. */
    transition: transform 200ms ease;
}
</style>
