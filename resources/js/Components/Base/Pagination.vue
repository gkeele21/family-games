<template>
    <div v-if="lastPage > 1 || showPerPage" class="flex flex-wrap items-center justify-between gap-4 py-6">
        <!-- Results info + per-page -->
        <div class="flex items-center gap-3 text-sm text-subtle">
            <span v-if="showInfo">
                <template v-if="client">{{ from }}–{{ to }} of {{ total }}</template>
                <template v-else>Showing {{ from }} to {{ to }} of {{ total }} results</template>
            </span>
            <template v-if="showPerPage">
                <span class="h-4 w-px bg-border"></span>
                <label class="flex items-center gap-2">
                    Per page:
                    <select
                        :value="perPage"
                        class="rounded-lg border-border bg-surface-inset py-1 pl-2 pr-7 text-sm text-body focus:border-primary focus:ring-primary"
                        @change="onPerPageChange"
                    >
                        <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
                    </select>
                </label>
            </template>
        </div>

        <!-- Navigation -->
        <nav v-if="lastPage > 1" class="flex items-center gap-2">
            <!-- Previous -->
            <button
                v-if="hasPrev"
                @click="goPrev"
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
                        v-else-if="page !== currentPage"
                        @click="goTo(page)"
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
                v-if="hasNext"
                @click="goNext"
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

// Two modes:
//   • server (default) — pass a Laravel paginator as `dataset`; navigation
//     visits page URLs via Inertia.
//   • client — pass `client`, `currentPage`, `perPage`, and `total`; the
//     component emits `update:page` instead of navigating, so the parent can
//     paginate an in-memory (e.g. client-filtered) list.
const props = defineProps({
    dataset: { type: Object, default: null },
    client: { type: Boolean, default: false },
    currentPage: { type: Number, default: 1 },
    perPage: { type: Number, default: 20 },
    total: { type: Number, default: 0 },
    // When provided (client mode), renders a "Per page:" dropdown with these options.
    perPageOptions: { type: Array, default: null },
    showInfo: { type: Boolean, default: true },
    maxVisiblePages: { type: Number, default: 12 },
    pageName: { type: String, default: 'page' },
    sectionId: { type: String, default: null },
});

const emit = defineEmits(['update:page', 'update:perPage']);

const showPerPage = computed(() => props.client && Array.isArray(props.perPageOptions) && props.perPageOptions.length > 0);

function onPerPageChange(e) {
    emit('update:perPage', Number(e.target.value));
}

// ---- Normalized view (works for either mode) ----
const currentPage = computed(() => (props.client ? props.currentPage : props.dataset.current_page));
const total = computed(() => (props.client ? props.total : props.dataset.total));
const lastPage = computed(() =>
    props.client ? Math.max(1, Math.ceil(props.total / props.perPage)) : props.dataset.last_page,
);
const from = computed(() =>
    props.client ? (total.value === 0 ? 0 : (currentPage.value - 1) * props.perPage + 1) : props.dataset.from,
);
const to = computed(() =>
    props.client ? Math.min(currentPage.value * props.perPage, total.value) : props.dataset.to,
);
const hasPrev = computed(() => (props.client ? currentPage.value > 1 : !!props.dataset.prev_page_url));
const hasNext = computed(() => (props.client ? currentPage.value < lastPage.value : !!props.dataset.next_page_url));

const visiblePages = computed(() => {
    const current = currentPage.value;
    const last = lastPage.value;
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

// ---- Navigation ----
function goTo(page) {
    if (props.client) {
        emit('update:page', page);
        return;
    }
    navigateTo(getPageUrl(page));
}
function goPrev() {
    if (props.client) {
        emit('update:page', currentPage.value - 1);
        return;
    }
    navigateTo(toRelativeUrl(props.dataset.prev_page_url));
}
function goNext() {
    if (props.client) {
        emit('update:page', currentPage.value + 1);
        return;
    }
    navigateTo(toRelativeUrl(props.dataset.next_page_url));
}

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
