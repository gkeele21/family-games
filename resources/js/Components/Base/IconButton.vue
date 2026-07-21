<template>
    <button
        type="button"
        :class="[
            'transition-colors disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center',
            showLabel ? labelClasses : (types[type]?.classes || types.default.classes)
        ]"
        :title="title || types[type]?.title"
        :disabled="disabled"
    >
        <Icon :name="types[type]?.icon || 'circle-question'" :size="size" />
        <span v-if="showLabel" class="ml-2">{{ types[type]?.title }}</span>
    </button>
</template>

<script setup>
import Icon from '@/Components/Base/Icon.vue';

const props = defineProps({
    type: { type: String, required: true },
    title: { type: String, default: '' },
    size: { type: String, default: 'sm' },
    disabled: { type: Boolean, default: false },
    showLabel: { type: Boolean, default: false },
});

const types = {
    edit: {
        icon: 'pen',
        title: 'Edit',
        classes: 'text-primary hover:text-primary-hover',
        labelClasses: 'px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-hover font-medium text-sm',
    },
    delete: {
        icon: 'trash',
        title: 'Delete',
        classes: 'text-danger hover:text-red-700',
        labelClasses: 'px-4 py-2 bg-danger text-white rounded-md hover:bg-red-700 font-medium text-sm',
    },
    view: {
        icon: 'eye',
        title: 'View',
        classes: 'text-primary hover:text-primary-hover',
        labelClasses: 'px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-hover font-medium text-sm',
    },
    download: {
        icon: 'download',
        title: 'Download',
        classes: 'text-primary hover:text-primary-hover',
        labelClasses: 'px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-hover font-medium text-sm',
    },
    default: {
        icon: 'circle-question',
        title: '',
        classes: 'text-gray-500 hover:text-gray-700',
        labelClasses: 'px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 font-medium text-sm',
    },
};

const labelClasses = types[props.type]?.labelClasses || types.default.labelClasses;
</script>
