<template>
    <div
        v-if="visible && (displayMessage || $slots.default)"
        :class="[
            'flex items-start gap-3 px-4 py-3 rounded-lg',
            variantClasses[displayVariant],
            fullWidth ? 'w-full' : ''
        ]"
        role="alert"
    >
        <Icon
            v-if="icon || defaultIcons[displayVariant]"
            :name="icon || defaultIcons[displayVariant]"
            class="flex-shrink-0 mt-0.5"
            size="sm"
        />
        <div class="flex-1 text-sm">
            <p v-if="title" class="font-semibold">{{ title }}</p>
            <div :class="title ? 'mt-1' : ''">
                <slot>{{ displayMessage }}</slot>
            </div>
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
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Base/Icon.vue';

const props = defineProps({
    show: { type: Boolean, default: true },
    title: { type: String, default: '' },
    message: { type: String, default: '' },
    variant: { type: String, default: 'info' }, // success, error, warning, info
    icon: { type: String, default: '' },
    dismissible: { type: Boolean, default: false },
    fullWidth: { type: Boolean, default: false },
    useFlash: { type: Boolean, default: false }, // Read from Inertia flash props
});

const emit = defineEmits(['close', 'update:show']);

// Flash message support (reads from $page.props.jetstream.flash or $page.props.flash)
const flashMessage = computed(() => {
    if (!props.useFlash) return null;
    const page = usePage();
    return page.props.jetstream?.flash?.banner || page.props.flash?.banner || null;
});

const flashStyle = computed(() => {
    if (!props.useFlash) return 'info';
    const page = usePage();
    const style = page.props.jetstream?.flash?.bannerStyle || page.props.flash?.bannerStyle || 'success';
    // Map JetStream styles to our variants
    return style === 'danger' ? 'error' : style;
});

const displayMessage = computed(() => props.useFlash ? flashMessage.value : props.message);
const displayVariant = computed(() => props.useFlash ? flashStyle.value : props.variant);

const visible = ref(props.show);

// Re-show when flash message changes
watch(flashMessage, () => {
    if (flashMessage.value) {
        visible.value = true;
    }
});

const variantClasses = {
    success: 'bg-success/10 text-success border border-success/20',
    error: 'bg-danger/10 text-danger border border-danger/20',
    warning: 'bg-warning/10 text-warning border border-warning/20',
    info: 'bg-info/10 text-info border border-info/20',
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

watch(() => props.show, (newVal) => {
    visible.value = newVal;
});
</script>
