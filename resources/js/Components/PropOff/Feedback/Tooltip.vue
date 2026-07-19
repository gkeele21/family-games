<template>
    <div class="relative inline-flex" ref="containerRef">
        <!-- Trigger -->
        <div
            @mouseenter="showOnHover && open()"
            @mouseleave="showOnHover && close()"
            @click="!showOnHover && toggle()"
            class="inline-flex items-center"
        >
            <slot name="trigger">
                <Icon
                    :name="icon"
                    :class="['cursor-pointer transition-colors', iconClass]"
                    :size="iconSize"
                />
            </slot>
        </div>

        <!-- Tooltip Content -->
        <teleport defer to="#propoff-app">
            <transition
                enter-active-class="transition ease-out duration-150"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="visible"
                    ref="tooltipRef"
                    :class="[
                        'fixed z-[9999] px-3 py-2 text-sm rounded-lg shadow-lg max-w-xs',
                        variantClasses[variant]
                    ]"
                    :style="tooltipStyle"
                    @mouseenter="showOnHover && open()"
                    @mouseleave="showOnHover && close()"
                >
                    <slot name="content">
                        {{ content }}
                    </slot>
                </div>
            </transition>
        </teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import Icon from '@/Components/PropOff/Base/Icon.vue';

const props = defineProps({
    content: { type: String, default: '' },
    icon: { type: String, default: 'circle-info' },
    iconClass: { type: String, default: 'text-info hover:text-info/80' },
    iconSize: { type: String, default: 'sm' },
    variant: { type: String, default: 'dark' }, // dark, light, info
    position: { type: String, default: 'top' }, // top, bottom, left, right
    showOnHover: { type: Boolean, default: true },
});

const visible = ref(false);
const positioned = ref(false);
const containerRef = ref(null);
const tooltipRef = ref(null);
const tooltipPosition = ref({ top: 0, left: 0 });

const variantClasses = {
    dark: 'bg-primary text-white',
    light: 'bg-white text-body border border-border',
    info: 'bg-info/10 text-info border border-info/20',
};

let hoverTimeout = null;

function open() {
    clearTimeout(hoverTimeout);
    positioned.value = false;
    visible.value = true;
    nextTick(() => {
        updatePosition();
        positioned.value = true;
    });
}

function close() {
    hoverTimeout = setTimeout(() => {
        visible.value = false;
    }, 100);
}

function toggle() {
    if (visible.value) {
        visible.value = false;
    } else {
        open();
    }
}

function updatePosition() {
    if (!containerRef.value || !tooltipRef.value) return;

    const triggerRect = containerRef.value.getBoundingClientRect();
    const tooltipRect = tooltipRef.value.getBoundingClientRect();
    const gap = 8;

    let top, left;

    switch (props.position) {
        case 'bottom':
            top = triggerRect.bottom + gap;
            left = triggerRect.left + (triggerRect.width / 2) - (tooltipRect.width / 2);
            break;
        case 'left':
            top = triggerRect.top + (triggerRect.height / 2) - (tooltipRect.height / 2);
            left = triggerRect.left - tooltipRect.width - gap;
            break;
        case 'right':
            top = triggerRect.top + (triggerRect.height / 2) - (tooltipRect.height / 2);
            left = triggerRect.right + gap;
            break;
        case 'top':
        default:
            top = triggerRect.top - tooltipRect.height - gap;
            left = triggerRect.left + (triggerRect.width / 2) - (tooltipRect.width / 2);
            break;
    }

    // Keep tooltip within viewport
    const padding = 8;
    left = Math.max(padding, Math.min(left, window.innerWidth - tooltipRect.width - padding));
    top = Math.max(padding, Math.min(top, window.innerHeight - tooltipRect.height - padding));

    tooltipPosition.value = { top, left };
}

const tooltipStyle = computed(() => ({
    top: `${tooltipPosition.value.top}px`,
    left: `${tooltipPosition.value.left}px`,
    visibility: positioned.value ? 'visible' : 'hidden',
}));

function handleClickOutside(e) {
    if (!props.showOnHover && visible.value) {
        if (!containerRef.value?.contains(e.target) && !tooltipRef.value?.contains(e.target)) {
            visible.value = false;
        }
    }
}

function handleEscape(e) {
    if (e.key === 'Escape' && visible.value) {
        visible.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    document.addEventListener('keydown', handleEscape);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    document.removeEventListener('keydown', handleEscape);
    clearTimeout(hoverTimeout);
});
</script>
