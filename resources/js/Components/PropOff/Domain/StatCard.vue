<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronRightIcon } from '@heroicons/vue/24/outline';

defineProps({
    title: {
        type: String,
        required: true,
    },
    count: {
        type: [Number, String],
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    href: {
        type: String,
        required: true,
    },
    borderColor: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'success', 'warning', 'danger', 'gray-dark', 'gray-light'].includes(value),
    },
});

const borderClasses = {
    'primary': 'border-l-primary',
    'success': 'border-l-success',
    'warning': 'border-l-warning',
    'danger': 'border-l-danger',
    'gray-dark': 'border-l-gray-dark',
    'gray-light': 'border-l-gray-light',
};

const textClasses = {
    'primary': 'text-primary',
    'success': 'text-success',
    'warning': 'text-warning',
    'danger': 'text-danger',
    'gray-dark': 'text-gray-dark',
    'gray-light': 'text-gray-light',
};
</script>

<template>
    <Link
        :href="href"
        class="group block bg-surface rounded-lg shadow-sm border border-border border-l-4 p-5 transition-all duration-200 hover:shadow-md hover:-translate-y-0.5"
        :class="borderClasses[borderColor]"
    >
        <div class="flex justify-between items-start">
            <div>
                <p class="text-3xl font-bold" :class="textClasses[borderColor]">
                    {{ count }}
                </p>
                <p class="text-base font-semibold text-body mt-1">
                    {{ title }}
                </p>
                <p v-if="subtitle" class="text-sm text-muted mt-1">
                    {{ subtitle }}
                </p>
            </div>
            <ChevronRightIcon
                class="h-5 w-5 text-muted opacity-0 group-hover:opacity-100 transition-opacity duration-200 mt-1"
            />
        </div>
    </Link>
</template>
