<template>
    <Modal :show="show" maxWidth="sm" @close="$emit('close')" showCloseButton>
        <div class="p-6 text-center">
            <!-- Lock Icon -->
            <div class="mx-auto w-16 h-16 bg-warning/20 rounded-full flex items-center justify-center mb-4">
                <Icon name="lock" size="2x" class="text-warning" />
            </div>

            <!-- Title -->
            <h3 class="text-xl font-bold text-body mb-2">
                {{ title }}
            </h3>

            <!-- Message -->
            <p class="text-muted mb-6">
                {{ message }}
            </p>

            <!-- Actions -->
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <Button
                    v-if="actionLabel && actionRoute"
                    @click="goToAction"
                    variant="primary"
                >
                    {{ actionLabel }}
                </Button>
                <Button
                    @click="$emit('close')"
                    variant="outline"
                >
                    Close
                </Button>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import Button from '@/Components/Base/Button.vue';
import Icon from '@/Components/Base/Icon.vue';
import Modal from '@/Components/Base/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Feature Locked' },
    message: { type: String, default: '' },
    actionLabel: { type: String, default: '' },
    actionRoute: { type: String, default: '' },
    actionRouteParams: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close']);

function goToAction() {
    if (props.actionRoute) {
        emit('close');
        // If actionRoute starts with '/', treat it as a full URL path
        // Otherwise, treat it as a route name and use the route() helper
        if (props.actionRoute.startsWith('/')) {
            router.visit(props.actionRoute);
        } else {
            router.visit(route(props.actionRoute, props.actionRouteParams));
        }
    }
}
</script>
