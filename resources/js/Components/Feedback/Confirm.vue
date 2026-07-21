<template>
    <Modal :show="show" :max-width="maxWidth" :closeable="closeable" @close="handleClose">
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-start gap-4">
                <div
                    v-if="icon"
                    :class="[
                        'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center',
                        iconBackgrounds[variant]
                    ]"
                >
                    <Icon :name="icon" :class="iconColors[variant]" />
                </div>
                <div class="flex-1">
                    <h3 v-if="title" class="text-lg font-semibold text-body">
                        {{ title }}
                    </h3>
                    <p v-if="message" class="mt-2 text-subtle whitespace-pre-line">
                        {{ message }}
                    </p>
                    <p v-else-if="messageHtml" class="mt-2 text-subtle whitespace-pre-line" v-html="messageHtml"></p>
                    <slot />
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex justify-end gap-3">
                <Button
                    v-if="showCancel"
                    variant="outline"
                    @click="handleCancel"
                >
                    {{ cancelText }}
                </Button>
                <Button
                    :variant="confirmVariant"
                    :loading="loading"
                    @click="handleConfirm"
                >
                    {{ confirmText }}
                </Button>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import Modal from '@/Components/Base/Modal.vue';
import Button from '@/Components/Base/Button.vue';
import Icon from '@/Components/Base/Icon.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    message: { type: String, default: '' },
    messageHtml: { type: String, default: '' },
    variant: { type: String, default: 'default' }, // default, danger, warning, info
    icon: { type: String, default: '' },
    confirmText: { type: String, default: 'Confirm' },
    cancelText: { type: String, default: 'Cancel' },
    showCancel: { type: Boolean, default: true },
    closeable: { type: Boolean, default: true },
    loading: { type: Boolean, default: false },
    maxWidth: { type: String, default: 'md' },
});

const emit = defineEmits(['confirm', 'cancel', 'close', 'update:show']);

const iconBackgrounds = {
    default: 'bg-primary/10',
    danger: 'bg-danger/10',
    warning: 'bg-warning/10',
    info: 'bg-info/10',
    success: 'bg-success/10',
};

const iconColors = {
    default: 'text-primary',
    danger: 'text-danger',
    warning: 'text-warning',
    info: 'text-info',
    success: 'text-success',
};

const confirmVariant = props.variant === 'danger' ? 'danger' : 'primary';

function handleConfirm() {
    emit('confirm');
}

function handleCancel() {
    emit('cancel');
    emit('update:show', false);
}

function handleClose() {
    emit('close');
    emit('update:show', false);
}
</script>
