<template>
    <teleport defer to="#propoff-app">
        <transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="transform opacity-0 translate-y-2"
            enter-to-class="transform opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="transform opacity-100 translate-y-0"
            leave-to-class="transform opacity-0 translate-y-2"
        >
            <div
                v-if="visible"
                :class="[
                    'fixed z-[9999] flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg max-w-sm',
                    positionClasses[position],
                    variantClasses[variant]
                ]"
            >
                <Icon v-if="icon || defaultIcons[variant]" :name="icon || defaultIcons[variant]" size="sm" />
                <div class="flex-1 text-sm font-medium">
                    <slot>{{ message }}</slot>
                </div>
                <button
                    v-if="dismissible"
                    @click="dismiss"
                    class="flex-shrink-0 opacity-70 hover:opacity-100 transition-opacity"
                    aria-label="Dismiss"
                >
                    <Icon name="xmark" size="sm" />
                </button>
            </div>
        </transition>
    </teleport>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import Icon from '@/Components/PropOff/Base/Icon.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    message: { type: String, default: '' },
    variant: { type: String, default: 'info' }, // success, error, warning, info
    position: { type: String, default: 'bottom-right' }, // top-right, top-left, bottom-right, bottom-left, top-center, bottom-center
    duration: { type: Number, default: 4000 }, // 0 = no auto-dismiss
    dismissible: { type: Boolean, default: true },
    icon: { type: String, default: '' },
});

const emit = defineEmits(['close', 'update:show']);

const visible = ref(props.show);
let timeout = null;

const positionClasses = {
    'top-right': 'top-4 right-4',
    'top-left': 'top-4 left-4',
    'bottom-right': 'bottom-4 right-4',
    'bottom-left': 'bottom-4 left-4',
    'top-center': 'top-4 left-1/2 -translate-x-1/2',
    'bottom-center': 'bottom-4 left-1/2 -translate-x-1/2',
};

const variantClasses = {
    success: 'bg-success text-white',
    error: 'bg-danger text-white',
    warning: 'bg-warning text-body',
    info: 'bg-info text-white',
};

const defaultIcons = {
    success: 'check-circle',
    error: 'circle-exclamation',
    warning: 'triangle-exclamation',
    info: 'circle-info',
};

function dismiss() {
    visible.value = false;
    emit('close');
    emit('update:show', false);
}

function startTimer() {
    if (props.duration > 0) {
        timeout = setTimeout(dismiss, props.duration);
    }
}

function clearTimer() {
    if (timeout) {
        clearTimeout(timeout);
        timeout = null;
    }
}

watch(() => props.show, (newVal) => {
    visible.value = newVal;
    if (newVal) {
        clearTimer();
        startTimer();
    }
});

onMounted(() => {
    if (props.show) {
        startTimer();
    }
});

onUnmounted(() => {
    clearTimer();
});
</script>
