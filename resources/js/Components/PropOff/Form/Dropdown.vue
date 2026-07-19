<template>
    <div class="relative" ref="containerRef">
        <div @click="toggle">
            <slot name="trigger" />
        </div>

        <!-- Full Screen Overlay -->
        <teleport defer to="#propoff-app">
            <div v-if="open" class="fixed inset-0 z-[9998]" @click="open = false" />
        </teleport>

        <teleport defer to="#propoff-app">
            <div
                v-if="open"
                ref="dropdownRef"
                class="fixed z-[9999] rounded-md shadow-lg bg-surface-elevated border border-border"
                :class="widthClass"
                :style="dropdownStyle"
                @click="open = false"
            >
                <div class="rounded-md" :class="contentClasses">
                    <slot name="content" />
                </div>
            </div>
        </teleport>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, nextTick } from 'vue';

const props = defineProps({
    align: { type: String, default: 'left' },
    width: { type: String, default: '48' },
    contentClasses: { type: Array, default: () => ['py-1'] },
    position: { type: String, default: 'bottom' }, // 'bottom' or 'top'
});

const open = ref(false);
const containerRef = ref(null);
const dropdownRef = ref(null);
const dropdownPosition = ref({ top: null, bottom: null, left: 0 });

const toggle = () => {
    if (!open.value) {
        updatePosition();
    }
    open.value = !open.value;
};

const updatePosition = () => {
    if (!containerRef.value) return;
    const rect = containerRef.value.getBoundingClientRect();
    const widthPx = getWidthPx();

    let left;
    if (props.align === 'right') {
        left = rect.right - widthPx;
    } else {
        left = rect.left;
    }

    if (props.position === 'top') {
        // Position above the trigger
        dropdownPosition.value = {
            top: null,
            bottom: window.innerHeight - rect.top + 8,
            left: left,
        };
    } else {
        // Position below the trigger (default)
        dropdownPosition.value = {
            top: rect.bottom + 8,
            bottom: null,
            left: left,
        };
    }
};

const getWidthPx = () => {
    const widthMap = { '48': 192, '60': 240, '72': 288, '96': 384 };
    return widthMap[props.width.toString()] || 192;
};

const dropdownStyle = computed(() => {
    const style = { left: `${dropdownPosition.value.left}px` };
    if (dropdownPosition.value.top !== null) {
        style.top = `${dropdownPosition.value.top}px`;
    }
    if (dropdownPosition.value.bottom !== null) {
        style.bottom = `${dropdownPosition.value.bottom}px`;
    }
    return style;
});

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));

const widthClass = computed(() => {
    return {
        '48': 'w-48',
        '60': 'w-60',
        '72': 'w-72',
        '96': 'w-96',
    }[props.width.toString()] || `w-${props.width}`;
});
</script>
