<template>
    <Link
        :href="route"
        class="group bg-white rounded-lg shadow hover:shadow-lg transition-shadow p-6 flex items-start space-x-4 border-l-4"
        :class="borderClass"
    >
        <div class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center" :class="iconBgClass">
            <i :class="icon" class="text-xl text-white"></i>
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-body">{{ title }}</h3>
                <i class="fa-solid fa-arrow-right text-muted opacity-0 group-hover:opacity-100 transition-opacity"></i>
            </div>
            <p class="text-sm text-subtle mt-1">{{ description }}</p>
        </div>
    </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    icon: { type: String, required: true },
    route: { type: String, required: true },
    color: { type: String, default: 'primary' },
});

const borderClass = computed(() => {
    const classes = {
        'primary': 'border-primary',
        'accent': 'border-warning',
        'dark': 'border-body',
        'danger': 'border-danger',
    };
    return classes[props.color] || 'border-primary';
});

const iconBgClass = computed(() => {
    const classes = {
        'primary': 'bg-primary',
        'accent': 'bg-warning',
        'dark': 'bg-body',
        'danger': 'bg-danger',
    };
    return classes[props.color] || 'bg-primary';
});
</script>
