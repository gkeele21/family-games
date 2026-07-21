<template>
    <Head title="Question Templates" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader title="Question Templates">
                <template #actions>
                    <Link :href="route('propoff.admin.question-templates.create')">
                        <Button variant="primary" size="sm" icon="plus">Create Template</Button>
                    </Link>
                </template>
            </PageHeader>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Search and Filter -->
                <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg mb-6 border border-border">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <TextField
                                v-model="form.search"
                                placeholder="Search templates..."
                                icon-left="magnifying-glass"
                                @input="debouncedFilter"
                            />
                            <Select
                                v-model="form.type"
                                :options="typeOptions"
                                placeholder="All Types"
                                allow-empty
                                empty-label="All Types"
                                @change="filterTemplates"
                            />
                            <Select
                                v-model="form.category"
                                :options="categoryOptions"
                                placeholder="All Categories"
                                allow-empty
                                empty-label="All Categories"
                                @change="filterTemplates"
                            />
                            <!-- Reorder Toggle -->
                            <div class="flex items-center justify-end">
                                <button
                                    @click="reorderMode = !reorderMode"
                                    class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg transition-colors"
                                    :class="reorderMode
                                        ? 'bg-primary text-white'
                                        : 'bg-surface-elevated text-muted hover:text-body'"
                                >
                                    <Icon name="grip-vertical" size="sm" />
                                    Reorder
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Templates Grid (default view) -->
                <div v-if="!reorderMode" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <Link
                        v-for="template in templates.data"
                        :key="template.id"
                        :href="route('propoff.admin.question-templates.edit', template.id)"
                        class="bg-surface overflow-hidden shadow-sm sm:rounded-lg border border-border border-l-4 border-l-info hover:border-primary transition-colors cursor-pointer block"
                    >
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-body mb-1">{{ template.title }}</h3>
                                    <span v-if="template.category" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-surface-elevated text-body">
                                        {{ template.category }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge
                                        :variant="template.question_type === 'multiple_choice' ? 'info-soft' :
                                                  template.question_type === 'yes_no' ? 'success-soft' :
                                                  template.question_type === 'numeric' ? 'warning-soft' : 'default'"
                                        size="sm"
                                    >
                                        {{ template.question_type?.replace('_', ' ') }}
                                    </Badge>
                                    <div @click.prevent.stop>
                                        <Dropdown align="right" width="48">
                                            <template #trigger>
                                                <button class="p-1 text-warning hover:text-warning/80 transition-colors">
                                                    <Icon name="ellipsis-vertical" />
                                                </button>
                                            </template>
                                            <template #content>
                                                <button
                                                    @click="duplicateTemplate(template.id)"
                                                    class="w-full px-4 py-2 text-left text-sm text-body hover:bg-surface-overlay flex items-center gap-2"
                                                >
                                                    <Icon name="copy" size="sm" class="text-warning" />
                                                    Duplicate
                                                </button>
                                            </template>
                                        </Dropdown>
                                    </div>
                                </div>
                            </div>

                            <p class="text-sm text-muted mb-4 line-clamp-2">{{ template.question_text }}</p>

                            <!-- Points Display -->
                            <div class="mb-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-xs font-medium text-body">Base Points:</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-success/10 text-success">
                                        {{ template.default_points || 0 }} pts
                                    </span>
                                </div>

                                <!-- Show bonus points if options have them -->
                                <div v-if="hasOptionBonusPoints(template)" class="text-xs text-warning">
                                    <span class="font-medium">Bonus Points:</span>
                                    <div class="ml-4 mt-1 space-y-0.5">
                                        <div v-for="(option, index) in getOptionsWithBonus(template)" :key="index" class="text-body">
                                            {{ option.label }}: <span class="font-semibold text-warning">+{{ option.points }} {{ option.points === 1 ? 'pt' : 'pts' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="template.variables && template.variables.length > 0" class="mb-4">
                                <p class="text-xs text-muted mb-1">Variables:</p>
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="variable in template.variables" :key="variable" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-primary/10 text-primary">
                                        {{ '{' + variable + '}' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </Link>
                </div>

                <!-- Templates List (reorder view) -->
                <div v-if="reorderMode" class="space-y-3">
                    <div
                        v-for="template in templates.data"
                        :key="template.id"
                        :id="`template-${template.id}`"
                        class="relative"
                    >
                        <!-- Drop indicator - before -->
                        <div
                            v-if="dropTarget === template.id && dropPosition === 'before'"
                            class="absolute -top-1.5 left-0 right-0 h-1 bg-primary rounded-full z-10"
                        />

                        <div
                            class="bg-surface overflow-hidden shadow-sm sm:rounded-lg border border-border border-l-4 border-l-info hover:border-primary transition-all"
                            :class="{
                                'opacity-50': draggedTemplate?.id === template.id,
                                'border-primary/50': dropTarget === template.id,
                                'ring-2 ring-primary ring-offset-2 ring-offset-surface': recentlyDroppedId === template.id,
                            }"
                            draggable="true"
                            @dragstart="handleDragStart(template)"
                            @drag="handleDrag"
                            @dragend="handleDragEnd"
                            @dragover="handleDragOver($event, template)"
                            @dragleave="handleDragLeave"
                            @drop="handleDrop(template)"
                        >
                            <div class="p-6 flex items-start gap-4">
                                <!-- Drag Handle -->
                                <div class="flex-shrink-0 cursor-grab active:cursor-grabbing text-subtle hover:text-muted pt-1">
                                    <Icon name="grip-vertical" />
                                </div>

                                <!-- Main Content -->
                                <Link
                                    :href="route('propoff.admin.question-templates.edit', template.id)"
                                    class="flex-1 min-w-0"
                                >
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex-1">
                                            <h3 class="text-lg font-semibold text-body mb-1">{{ template.title }}</h3>
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span v-if="template.category" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-surface-elevated text-body">
                                                    {{ template.category }}
                                                </span>
                                                <Badge
                                                    :variant="template.question_type === 'multiple_choice' ? 'info-soft' :
                                                              template.question_type === 'yes_no' ? 'success-soft' :
                                                              template.question_type === 'numeric' ? 'warning-soft' : 'default'"
                                                    size="sm"
                                                >
                                                    {{ template.question_type?.replace('_', ' ') }}
                                                </Badge>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-success/10 text-success">
                                                    {{ template.default_points || 0 }} pts
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <p class="text-sm text-muted mb-3 line-clamp-2">{{ template.question_text }}</p>

                                    <div class="flex items-center gap-4 flex-wrap">
                                        <!-- Show bonus points if options have them -->
                                        <div v-if="hasOptionBonusPoints(template)" class="text-xs text-warning">
                                            <span class="font-medium">Bonus:</span>
                                            <span v-for="(option, index) in getOptionsWithBonus(template)" :key="index" class="ml-1">
                                                {{ option.label }} +{{ option.points }}{{ index < getOptionsWithBonus(template).length - 1 ? ',' : '' }}
                                            </span>
                                        </div>

                                        <div v-if="template.variables && template.variables.length > 0" class="flex items-center gap-1">
                                            <span class="text-xs text-muted">Variables:</span>
                                            <span v-for="variable in template.variables" :key="variable" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-primary/10 text-primary">
                                                {{ '{' + variable + '}' }}
                                            </span>
                                        </div>
                                    </div>
                                </Link>

                                <!-- Actions -->
                                <div class="flex-shrink-0" @click.stop>
                                    <Dropdown align="right" width="48">
                                        <template #trigger>
                                            <button class="p-1 text-warning hover:text-warning/80 transition-colors">
                                                <Icon name="ellipsis-vertical" />
                                            </button>
                                        </template>
                                        <template #content>
                                            <button
                                                @click="duplicateTemplate(template.id)"
                                                class="w-full px-4 py-2 text-left text-sm text-body hover:bg-surface-overlay flex items-center gap-2"
                                            >
                                                <Icon name="copy" size="sm" class="text-warning" />
                                                Duplicate
                                            </button>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                        </div>

                        <!-- Drop indicator - after -->
                        <div
                            v-if="dropTarget === template.id && dropPosition === 'after'"
                            class="absolute -bottom-1.5 left-0 right-0 h-1 bg-primary rounded-full z-10"
                        />
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="templates.data.length === 0" class="bg-surface overflow-hidden shadow-sm sm:rounded-lg border border-border">
                    <div class="p-12 text-center">
                        <Icon name="file-lines" size="3x" class="mx-auto text-muted" />
                        <h3 class="mt-2 text-sm font-medium text-body">No templates found</h3>
                        <p class="mt-1 text-sm text-muted">Get started by creating a new question template.</p>
                        <div class="mt-6">
                            <Link :href="route('propoff.admin.question-templates.create')">
                                <Button variant="primary" icon="plus">Create Template</Button>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <Pagination :dataset="templates" class="mt-6" />
            </div>
        </div>

        <!-- Toast Notification -->
        <Toast
            :show="showToast"
            :message="toastMessage"
            @close="showToast = false"
        />
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import PageHeader from '@/Components/Base/PageHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Button from '@/Components/Base/Button.vue';
import Badge from '@/Components/Base/Badge.vue';
import Icon from '@/Components/Base/Icon.vue';
import TextField from '@/Components/Form/TextField.vue';
import Select from '@/Components/Form/Select.vue';
import Dropdown from '@/Components/Form/Dropdown.vue';
import Pagination from '@/Components/Base/Pagination.vue';
import Toast from '@/Components/Feedback/Toast.vue';
import { ref, computed, watch } from 'vue';
import { debounce } from 'lodash';

const props = defineProps({
    templates: Object,  // Pagination object from Laravel
    categories: Array,
    filters: Object,
});

// Initialize form with current filters
const form = ref({
    search: props.filters?.search || '',
    type: props.filters?.type || '',
    category: props.filters?.category || '',
});

// Options for type select
const typeOptions = [
    { value: 'multiple_choice', label: 'Multiple Choice' },
    { value: 'yes_no', label: 'Yes/No' },
    { value: 'numeric', label: 'Numeric' },
    { value: 'text', label: 'Text' },
    { value: 'ranked_answers', label: 'Ranked Answers' },
];

// Options for category select
const categoryOptions = computed(() =>
    props.categories.map(cat => ({ value: cat, label: cat }))
);

// Server-side filter function
const filterTemplates = () => {
    router.get(route('propoff.admin.question-templates.index'), {
        search: form.value.search || undefined,
        type: form.value.type || undefined,
        category: form.value.category || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// Debounced version for search input
const debouncedFilter = debounce(() => {
    filterTemplates();
}, 300);

const duplicateTemplate = (id) => {
    router.post(route('propoff.admin.question-templates.duplicate', id));
};

// Reorder mode toggle
const reorderMode = ref(false);

// Toast
const toastMessage = ref('');
const showToast = ref(false);

const showToastMessage = (message) => {
    toastMessage.value = message;
    showToast.value = true;
    setTimeout(() => {
        showToast.value = false;
    }, 3000);
};

// Drag and drop reordering
const draggedTemplate = ref(null);
const dropTarget = ref(null);
const dropPosition = ref(null);
let scrollInterval = null;

const handleDragStart = (template) => {
    draggedTemplate.value = template;
};

const handleDrag = (e) => {
    const scrollThreshold = 100;
    const scrollSpeed = 15;

    if (scrollInterval) {
        clearInterval(scrollInterval);
        scrollInterval = null;
    }

    if (e.clientY === 0) return;

    const viewportHeight = window.innerHeight;

    if (e.clientY < scrollThreshold) {
        scrollInterval = setInterval(() => {
            window.scrollBy(0, -scrollSpeed);
        }, 16);
    } else if (e.clientY > viewportHeight - scrollThreshold) {
        scrollInterval = setInterval(() => {
            window.scrollBy(0, scrollSpeed);
        }, 16);
    }
};

const handleDragEnd = () => {
    if (scrollInterval) {
        clearInterval(scrollInterval);
        scrollInterval = null;
    }
    draggedTemplate.value = null;
    dropTarget.value = null;
    dropPosition.value = null;
};

const handleDragOver = (e, template) => {
    e.preventDefault();
    if (!draggedTemplate.value || draggedTemplate.value.id === template.id) {
        dropTarget.value = null;
        dropPosition.value = null;
        return;
    }

    const rect = e.currentTarget.getBoundingClientRect();
    const midpoint = rect.top + rect.height / 2;
    dropTarget.value = template.id;
    dropPosition.value = e.clientY < midpoint ? 'before' : 'after';
};

const handleDragLeave = (e) => {
    if (!e.currentTarget.contains(e.relatedTarget)) {
        dropTarget.value = null;
        dropPosition.value = null;
    }
};

const recentlyDroppedId = ref(null);

const handleDrop = (targetTemplate) => {
    const currentDropPosition = dropPosition.value;
    const droppedTemplateId = draggedTemplate.value?.id;

    dropTarget.value = null;
    dropPosition.value = null;

    if (!draggedTemplate.value || draggedTemplate.value.id === targetTemplate.id) {
        return;
    }

    let newOrder = targetTemplate.display_order;
    if (currentDropPosition === 'after') {
        newOrder = targetTemplate.display_order + 1;
    }

    router.post(
        route('propoff.admin.question-templates.reorder'),
        {
            template_id: draggedTemplate.value.id,
            new_order: newOrder,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showToastMessage('Templates reordered');
                recentlyDroppedId.value = droppedTemplateId;
                setTimeout(() => {
                    const element = document.getElementById(`template-${droppedTemplateId}`);
                    if (element) {
                        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    setTimeout(() => {
                        recentlyDroppedId.value = null;
                    }, 1500);
                }, 300);
            },
        }
    );

    draggedTemplate.value = null;
};

// Helper function to check if template has options with bonus points
const hasOptionBonusPoints = (template) => {
    if (!template.default_options) return false;

    try {
        const options = typeof template.default_options === 'string'
            ? JSON.parse(template.default_options)
            : template.default_options;

        if (!Array.isArray(options)) return false;

        return options.some(option => option.points && option.points > 0);
    } catch (e) {
        return false;
    }
};

// Helper function to get options that have bonus points
const getOptionsWithBonus = (template) => {
    if (!template.default_options) return [];

    try {
        const options = typeof template.default_options === 'string'
            ? JSON.parse(template.default_options)
            : template.default_options;

        if (!Array.isArray(options)) return [];

        return options.filter(option => option.points && option.points > 0);
    } catch (e) {
        return [];
    }
};
</script>
