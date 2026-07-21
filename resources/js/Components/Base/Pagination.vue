<template>
    <div v-if="dataset.last_page > 1" class="flex items-center justify-between py-6">
        <!-- Results info -->
        <div v-if="showInfo" class="text-sm text-subtle">
            Showing {{ dataset.from }} to {{ dataset.to }} of {{ dataset.total }} results
        </div>

        <!-- Navigation -->
        <nav class="flex items-center gap-2">
            <!-- Previous -->
            <button
                v-if="dataset.prev_page_url"
                @click="navigateTo(toRelativeUrl(dataset.prev_page_url))"
                class="flex items-center gap-1 text-sm text-primary hover:text-warning transition-colors"
            >
                <Icon name="chevron-left" size="sm" />
                <span>Previous</span>
            </button>
            <span v-else class="flex items-center gap-1 text-sm text-muted cursor-not-allowed">
                <Icon name="chevron-left" size="sm" />
                <span>Previous</span>
            </span>

            <!-- Page Numbers -->
            <div class="flex items-center gap-1 mx-2">
                <template v-for="page in visiblePages" :key="page">
                    <span v-if="page === '...'" class="px-2 text-subtle">...</span>
                    <button
                        v-else-if="page !== dataset.current_page"
                        @click="navigateTo(getPageUrl(page))"
                        class="px-2 py-1 text-sm text-body hover:text-warning transition-colors"
                    >
                        {{ page }}
                    </button>
                    <span v-else class="px-2 py-1 text-sm font-semibold bg-secondary text-white rounded">
                        {{ page }}
                    </span>
                </template>
            </div>

            <!-- Next -->
            <button
                v-if="dataset.next_page_url"
                @click="navigateTo(toRelativeUrl(dataset.next_page_url))"
                class="flex items-center gap-1 text-sm text-primary hover:text-warning transition-colors"
            >
                <span>Next</span>
                <Icon name="chevron-right" size="sm" />
            </button>
            <span v-else class="flex items-center gap-1 text-sm text-muted cursor-not-allowed">
                <span>Next</span>
                <Icon name="chevron-right" size="sm" />
            </span>
        </nav>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icon from '@/Components/Base/Icon.vue';

const props = defineProps({
    dataset: { type: Object, required: true },
    showInfo: { type: Boolean, default: true },
    maxVisiblePages: { type: Number, default: 12 },
    pageName: { type: String, default: 'page' },
    sectionId: { type: String, default: null },
});

const visiblePages = computed(() => {
    const current = props.dataset.current_page;
    const last = props.dataset.last_page;
    const pages = [];

    if (last <= props.maxVisiblePages) {
        for (let i = 1; i <= last; i++) {
            pages.push(i);
        }
    } else {
        pages.push(1);

        if (current > 3) {
            pages.push('...');
        }

        const start = Math.max(2, current - 1);
        const end = Math.min(last - 1, current + 1);

        for (let i = start; i <= end; i++) {
            pages.push(i);
        }

        if (current < last - 2) {
            pages.push('...');
        }

        pages.push(last);
    }

    return pages;
});

function getPageUrl(page) {
    const url = new URL(window.location.href);
    url.searchParams.set(props.pageName, page);
    return url.pathname + url.search;
}

// Convert absolute URLs from Laravel paginator to relative URLs
function toRelativeUrl(absoluteUrl) {
    if (!absoluteUrl) return null;
    try {
        const url = new URL(absoluteUrl);
        return url.pathname + url.search;
    } catch {
        return absoluteUrl;
    }
}

function navigateTo(url) {
    router.visit(url, {
        preserveScroll: !props.sectionId,
        onSuccess: () => {
            if (props.sectionId) {
                const element = document.getElementById(props.sectionId);
                if (element) {
                    element.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        },
    });
}
</script>
