<template>
    <div class="bg-surface border-b border-border shadow-sm sticky top-16 z-40">
        <div class="py-2 sm:py-3 px-4 sm:px-6 lg:px-8">
            <!-- Breadcrumb (compact, above title) -->
            <nav v-if="crumbs && crumbs.length > 0" class="flex items-center gap-1 text-xs text-muted mb-1">
                <template v-for="(crumb, index) in crumbs" :key="index">
                    <Link
                        v-if="crumb.href"
                        :href="crumb.href"
                        class="hover:text-primary transition-colors cursor-pointer"
                    >
                        {{ crumb.label }}
                    </Link>
                    <span v-else>{{ crumb.label }}</span>
                    <Icon v-if="index < crumbs.length - 1" name="chevron-right" size="xs" class="text-subtle" />
                </template>
            </nav>

            <!-- Title Section with Actions -->
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2 sm:gap-4">
                <!-- Left side: Title and optional subtitle/metadata -->
                <div class="flex-1 min-w-0">
                    <h1 class="font-bold text-lg sm:text-xl text-body leading-tight flex items-center flex-wrap gap-1.5 sm:gap-2">
                        {{ title }}
                        <slot name="titleSuffix" />
                    </h1>

                    <!-- Subtitle text (if provided as prop) -->
                    <p v-if="subtitle" class="text-sm text-muted mt-1">
                        {{ subtitle }}
                    </p>

                    <!-- Metadata slot for custom content like event status, date, etc. -->
                    <div v-if="$slots.metadata" class="mt-1 text-sm text-muted">
                        <slot name="metadata" />
                    </div>
                </div>

                <!-- Right side: Action buttons -->
                <div v-if="$slots.actions" class="flex items-center gap-1.5 sm:gap-3 flex-shrink-0 flex-wrap">
                    <slot name="actions" />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Base/Icon.vue';

defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: null,
    },
    crumbs: {
        type: Array,
        default: () => [],
    },
});
</script>
