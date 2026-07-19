<template>
    <component :is="linkTo ? 'a' : 'div'" :href="linkTo" class="inline-flex items-center" :class="gapClass">
        <!-- Diamond Icon -->
        <svg
            v-if="showIcon"
            :width="iconSize.width"
            :height="iconSize.height"
            :viewBox="`0 0 ${iconSize.viewBox} ${iconSize.viewBoxHeight}`"
            class="flex-shrink-0"
        >
            <polygon
                :points="iconSize.points"
                class="fill-primary"
            />
        </svg>

        <!-- Wordmark -->
        <span
            v-if="showWordmark"
            class="font-logo font-bold"
            :class="textSizeClass"
        >
            <span class="text-body">PROP</span><span class="text-primary">OFF</span>
        </span>
    </component>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    /**
     * Logo variant
     * - full: Diamond icon + wordmark (default)
     * - icon: Diamond icon only
     * - wordmark: Wordmark only
     */
    variant: {
        type: String,
        default: 'full',
        validator: (v) => ['full', 'icon', 'wordmark'].includes(v),
    },

    /**
     * Size preset
     * - sm: Small (nav compact)
     * - md: Medium (nav default)
     * - lg: Large (hero, login)
     * - xl: Extra large (splash screens)
     */
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg', 'xl'].includes(v),
    },

    /**
     * Optional link - makes logo clickable
     */
    linkTo: {
        type: String,
        default: null,
    },
});

const showIcon = computed(() => ['full', 'icon'].includes(props.variant));
const showWordmark = computed(() => ['full', 'wordmark'].includes(props.variant));

// Size configurations
const sizeConfigs = {
    sm: {
        icon: { width: 24, height: 29, viewBox: 40, viewBoxHeight: 48, points: '20,2 38,24 20,46 2,24' },
        text: 'text-xl',
        gap: 'gap-2',
    },
    md: {
        icon: { width: 32, height: 38, viewBox: 40, viewBoxHeight: 48, points: '20,2 38,24 20,46 2,24' },
        text: 'text-2xl',
        gap: 'gap-2.5',
    },
    lg: {
        icon: { width: 40, height: 48, viewBox: 40, viewBoxHeight: 48, points: '20,2 38,24 20,46 2,24' },
        text: 'text-[42px]',
        gap: 'gap-3',
    },
    xl: {
        icon: { width: 56, height: 67, viewBox: 40, viewBoxHeight: 48, points: '20,2 38,24 20,46 2,24' },
        text: 'text-6xl',
        gap: 'gap-4',
    },
};

const iconSize = computed(() => sizeConfigs[props.size].icon);
const textSizeClass = computed(() => sizeConfigs[props.size].text);
const gapClass = computed(() => props.variant === 'full' ? sizeConfigs[props.size].gap : '');
</script>
