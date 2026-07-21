<template>
    <div :class="['rounded-lg shadow overflow-hidden', bordered ? 'border border-border' : '', bgClass]">
        <!-- Header -->
        <div
            v-if="title || $slots.actions"
            class="px-6 py-4 border-b border-border flex justify-between items-center"
        >
            <h3 v-if="title" class="text-lg font-semibold text-body">{{ title }}</h3>
            <div v-if="$slots.actions" class="flex items-center gap-3">
                <slot name="actions" />
            </div>
        </div>

        <!-- Filters -->
        <div v-if="$slots.filters" class="px-6 py-4 bg-white border-b border-border">
            <slot name="filters" />
        </div>

        <!-- Loading overlay -->
        <div v-if="loading" class="relative">
            <div class="absolute inset-0 bg-white/50 flex items-center justify-center z-10">
                <Icon name="spinner" class="animate-spin text-primary" size="lg" />
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto" :class="{ 'opacity-50': loading }">
            <table class="min-w-full divide-y divide-border">
                <thead :class="headerClass">
                    <tr v-if="$slots['column-groups']">
                        <slot name="column-groups" />
                    </tr>
                    <tr>
                        <slot name="columns" />
                    </tr>
                </thead>
                <tbody :class="['bg-white divide-y divide-border', clickableRows ? 'clickable-rows' : '']">
                    <template v-if="!isEmpty">
                        <slot name="rows" />
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Empty State -->
        <div v-if="isEmpty && !loading" class="px-6 py-12 text-center">
            <slot name="empty">
                <Icon name="inbox" size="xl" class="text-muted mb-3" />
                <p class="text-muted">{{ emptyText }}</p>
            </slot>
        </div>

        <!-- Footer / Pagination -->
        <div v-if="$slots.pagination || $slots.footer" class="px-6 py-4 border-t border-border bg-white">
            <slot name="pagination" />
            <slot name="footer" />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import Icon from '@/Components/Base/Icon.vue';

const props = defineProps({
    title: { type: String, default: '' },
    isEmpty: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    emptyText: { type: String, default: 'No data available' },
    bordered: { type: Boolean, default: false },
    variant: { type: String, default: 'default' }, // 'default', 'striped', 'compact'
    headerVariant: { type: String, default: 'primary' }, // 'primary', 'secondary', 'dark', 'light'
    clickableRows: { type: Boolean, default: false },
});

const bgClass = computed(() => {
    return 'bg-white';
});

const headerVariants = {
    primary: 'bg-primary text-white', // Dark OraTek Blue (#22418E)
    secondary: 'bg-secondary text-white',   // Clinical Teal from style guide
    blue: 'bg-primary text-white',          // OraTek Blue (#2154BE)
    dark: 'bg-black text-white',     // Dark neutral
    light: 'bg-surface text-body',
};

const headerClass = computed(() => headerVariants[props.headerVariant] || headerVariants.primary);
</script>
