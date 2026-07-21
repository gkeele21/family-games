<template>
    <Head :title="`Import Questions - ${event.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                title="Import from Templates"
                :crumbs="[
                    { label: 'Events', href: route('propoff.admin.events.index') },
                    { label: event.name, href: route('propoff.admin.events.show', event.id) },
                    { label: 'Import Templates' }
                ]"
            />
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                <!-- Event Context -->
                <div class="bg-primary/10 border border-primary/30 rounded-lg p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <Icon name="circle-info" class="text-primary mt-0.5" />
                        <div>
                            <h3 class="font-semibold text-primary">{{ event.name }}</h3>
                            <p class="text-sm text-primary mt-1">
                                Current Questions: {{ currentQuestions.length }} | Event Date: {{ formatDate(event?.event_date) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- LEFT: Template Search -->
                    <div class="lg:col-span-2">
                        <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg border border-border">
                            <div class="border-b border-border p-6">
                                <h3 class="text-lg font-semibold text-body mb-4">
                                    <Icon name="file-circle-plus" class="inline mr-2" />
                                    Find Template Questions
                                </h3>

                                <!-- Category Search Input -->
                                <div class="mb-6">
                                    <div class="flex gap-2 items-end">
                                        <div class="flex-1">
                                            <TextField
                                                v-model="categorySearch"
                                                label="Search by Category"
                                                placeholder="Enter category (e.g., football, nfl, sports)"
                                                icon-left="magnifying-glass"
                                                @keyup.enter="findTemplatesByCategory"
                                            />
                                        </div>
                                        <Button
                                            variant="primary"
                                            size="md"
                                            :disabled="!categorySearch.trim() || isSearching"
                                            :loading="isSearching"
                                            @click="findTemplatesByCategory"
                                        >
                                            Find
                                        </Button>
                                    </div>
                                </div>

                                <!-- Search Results -->
                                <div v-if="searchPerformed">
                                    <div v-if="filteredTemplates.length === 0" class="text-center py-8 text-muted">
                                        No templates found for category "{{ lastSearchTerm }}".
                                    </div>

                                    <div v-else class="space-y-3">
                                        <p class="text-sm text-body mb-3">
                                            Found {{ filteredTemplates.length }} template{{ filteredTemplates.length !== 1 ? 's' : '' }} for "{{ lastSearchTerm }}"
                                        </p>

                                        <!-- Select All / Deselect All -->
                                        <div class="flex gap-2 pb-4 border-b border-border">
                                            <Button variant="primary" size="sm" @click="selectAllTemplates">
                                                Select All
                                            </Button>
                                            <Button variant="muted" size="sm" @click="deselectAllTemplates">
                                                Deselect All
                                            </Button>
                                            <Button
                                                variant="success"
                                                size="sm"
                                                class="ml-auto"
                                                :disabled="selectedTemplates.length === 0"
                                                @click="bulkCreateSelected"
                                            >
                                                Import {{ selectedTemplates.length }} Selected
                                            </Button>
                                        </div>

                                        <!-- Template List -->
                                        <div class="space-y-2 max-h-[500px] overflow-y-auto">
                                            <div v-for="template in filteredTemplates" :key="template.id"
                                                 class="p-3 border border-border rounded hover:bg-surface-overlay">

                                                <!-- Header row: Checkbox + Title + Badge -->
                                                <div class="flex items-center gap-3 mb-2">
                                                    <Checkbox
                                                        :model-value="isTemplateSelected(template.id)"
                                                        @update:model-value="toggleTemplateSelection(template)"
                                                    />
                                                    <h4 class="font-semibold text-body">{{ template.title }}</h4>
                                                    <Badge :variant="typeBadgeVariant(template.question_type)" size="sm">
                                                        {{ formatType(template.question_type) }}
                                                    </Badge>
                                                </div>

                                                <!-- Template Info (indented to align with title) -->
                                                <div class="pl-8">
                                                    <p class="text-sm text-muted mb-2">{{ template.question_text }}</p>

                                                    <!-- Variables Badge -->
                                                    <div v-if="template.variables?.length">
                                                        <Badge variant="warning-soft" size="sm">
                                                            {{ template.variables.length }} variable{{ template.variables.length !== 1 ? 's' : '' }}:
                                                            {{ template.variables.join(', ') }}
                                                        </Badge>
                                                    </div>

                                                    <!-- Template Answers Preview -->
                                                    <div v-if="template.template_answers?.length" class="mt-2 text-xs">
                                                        <div class="bg-primary/10 border border-primary/30 rounded p-2">
                                                            <p class="font-semibold text-primary mb-1">Ranked Answers ({{ template.template_answers.length }}):</p>
                                                            <ol class="list-decimal list-inside text-primary space-y-0.5">
                                                                <li v-for="answer in template.template_answers" :key="answer.id">
                                                                    {{ answer.answer_text }}
                                                                </li>
                                                            </ol>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="text-center py-8 text-muted">
                                    <p>Enter a category and click "Find" to search for template questions.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT: Current Questions -->
                    <div>
                        <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg border border-border relative">
                            <Button
                                v-if="currentQuestions.length > 0"
                                variant="danger"
                                size="xs"
                                class="absolute top-3 right-3"
                                @click="deleteAllQuestions"
                            >
                                Delete All
                            </Button>
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-body mb-4">
                                    <Icon name="list" class="inline mr-2" />
                                    Current Questions ({{ currentQuestions.length }})
                                </h3>

                                <div v-if="currentQuestions.length === 0" class="text-center py-8 text-muted">
                                    <p>No questions added yet.</p>
                                    <p class="text-sm mt-2">Search and import templates to get started.</p>
                                </div>

                                <div v-else class="space-y-2 max-h-96 overflow-y-auto">
                                    <div v-for="(question, index) in currentQuestions" :key="question.id"
                                         class="p-2 bg-surface-elevated rounded border border-border text-sm">

                                        <div class="flex justify-between items-start gap-2">
                                            <div class="flex-1">
                                                <p class="font-semibold text-body line-clamp-2">
                                                    {{ question.question_text }}
                                                </p>
                                                <p class="text-xs text-muted mt-1">
                                                    Order: {{ question.display_order }} | {{ question.points }} {{ question.points === 1 ? 'point' : 'points' }}
                                                </p>
                                            </div>
                                            <button
                                                @click="deleteQuestion(question.id)"
                                                class="text-danger hover:text-danger/80 flex-shrink-0"
                                            >
                                                <Icon name="trash" size="sm" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Consolidated Variable Input Modal -->
                <Modal :show="showConsolidatedVariableModal" max-width="3xl" @close="closeConsolidatedVariableModal">
                    <div class="p-6">
                        <!-- Header -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-body">
                                Fill in Variables for {{ selectedTemplates.length }} Question{{ selectedTemplates.length !== 1 ? 's' : '' }}
                            </h3>
                            <p class="mt-1 text-sm text-muted">
                                The following variables were found across your selected templates.
                                Fill them out once, and they'll be applied to all questions.
                            </p>
                        </div>

                        <!-- Body -->
                        <div class="space-y-4 max-h-[50vh] overflow-y-auto px-1">
                            <TextField
                                v-for="variable in distinctVariables"
                                :key="variable"
                                v-model="consolidatedVariables[variable]"
                                :label="variable"
                                :hint="`Used in ${getTemplateCountForVariable(variable)} question(s)`"
                                :placeholder="`Enter value for ${variable}`"
                            />
                        </div>

                        <!-- Footer -->
                        <div class="mt-6 flex items-center justify-end gap-3">
                            <Button variant="outline" @click="closeConsolidatedVariableModal">
                                Cancel
                            </Button>
                            <Button
                                variant="primary"
                                :disabled="!allVariablesFilled"
                                @click="submitConsolidatedImport"
                            >
                                Import {{ selectedTemplates.length }} Question{{ selectedTemplates.length !== 1 ? 's' : '' }}
                            </Button>
                        </div>
                    </div>
                </Modal>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import PageHeader from '@/Components/Base/PageHeader.vue';
import TextField from '@/Components/Form/TextField.vue';
import Checkbox from '@/Components/Form/Checkbox.vue';
import Button from '@/Components/Base/Button.vue';
import Badge from '@/Components/Base/Badge.vue';
import Modal from '@/Components/Base/Modal.vue';
import Icon from '@/Components/Base/Icon.vue';
import axios from 'axios';

const props = defineProps({
    event: Object,
    currentQuestions: Array,
    nextOrder: Number,
});

// Category Search State
const categorySearch = ref('');
const lastSearchTerm = ref('');
const searchPerformed = ref(false);
const filteredTemplates = ref([]);
const isSearching = ref(false);

// Template Selection State
const selectedTemplates = ref([]);

// Consolidated Variable Modal State
const showConsolidatedVariableModal = ref(false);
const consolidatedVariables = ref({});

// Computed: Get all distinct variables across selected templates
const distinctVariables = computed(() => {
    const variableSet = new Set();

    selectedTemplates.value.forEach(template => {
        if (template.variables && Array.isArray(template.variables)) {
            template.variables.forEach(v => variableSet.add(v));
        }
    });

    return Array.from(variableSet).sort();
});

// Computed: Check if all variables are filled
const allVariablesFilled = computed(() => {
    return distinctVariables.value.every(variable => {
        return consolidatedVariables.value[variable] &&
               consolidatedVariables.value[variable].trim() !== '';
    });
});

// Methods

// Search for templates by category
const findTemplatesByCategory = async () => {
    if (!categorySearch.value.trim()) {
        return;
    }

    isSearching.value = true;

    try {
        const response = await axios.get(
            route('propoff.admin.events.event-questions.searchTemplates', props.event.id),
            {
                params: { category: categorySearch.value.trim() }
            }
        );

        filteredTemplates.value = response.data.templates;
        lastSearchTerm.value = response.data.search_term;
        searchPerformed.value = true;
    } catch (error) {
        console.error('Error searching templates:', error);
        alert('Error searching for templates. Please try again.');
    } finally {
        isSearching.value = false;
    }
};

// Toggle template selection
const toggleTemplateSelection = (template) => {
    const index = selectedTemplates.value.findIndex(t => t.id === template.id);
    if (index > -1) {
        selectedTemplates.value.splice(index, 1);
    } else {
        selectedTemplates.value.push(template);
    }
};

// Check if template is selected
const isTemplateSelected = (templateId) => {
    return selectedTemplates.value.some(t => t.id === templateId);
};

// Select all templates
const selectAllTemplates = () => {
    selectedTemplates.value = [...filteredTemplates.value];
};

// Deselect all templates
const deselectAllTemplates = () => {
    selectedTemplates.value = [];
};

// Bulk create selected templates
const bulkCreateSelected = () => {
    if (selectedTemplates.value.length === 0) {
        return;
    }

    // Check if any selected templates have variables
    const hasVariables = selectedTemplates.value.some(t =>
        t.variables && t.variables.length > 0
    );

    if (hasVariables) {
        openConsolidatedVariableModal();
    } else {
        // No variables, import directly
        submitBulkImport();
    }
};

// Open consolidated variable modal
const openConsolidatedVariableModal = () => {
    // Initialize consolidated variables
    consolidatedVariables.value = {};
    distinctVariables.value.forEach(variable => {
        consolidatedVariables.value[variable] = '';
    });

    showConsolidatedVariableModal.value = true;
};

// Close consolidated variable modal
const closeConsolidatedVariableModal = () => {
    showConsolidatedVariableModal.value = false;
    consolidatedVariables.value = {};
};

// Helper: Count how many templates use a variable
const getTemplateCountForVariable = (variable) => {
    return selectedTemplates.value.filter(template => {
        return template.variables && template.variables.includes(variable);
    }).length;
};

// Submit bulk import with consolidated variables
const submitConsolidatedImport = () => {
    const payload = {
        templates: selectedTemplates.value.map(template => ({
            template_id: template.id,
            variable_values: template.variables
                ? template.variables.reduce((acc, variable) => {
                    acc[variable] = consolidatedVariables.value[variable] || '';
                    return acc;
                }, {})
                : {}
        }))
    };

    router.post(
        route('propoff.admin.events.event-questions.bulkCreateFromTemplates', props.event.id),
        payload,
        {
            onSuccess: () => {
                closeConsolidatedVariableModal();
                selectedTemplates.value = [];
                categorySearch.value = '';
                searchPerformed.value = false;
                filteredTemplates.value = [];
            },
            onError: (errors) => {
                console.error('Error importing templates:', errors);
                alert('Error importing templates. Please try again.');
            }
        }
    );
};

// Submit bulk import without variables
const submitBulkImport = () => {
    const payload = {
        templates: selectedTemplates.value.map(template => ({
            template_id: template.id,
            variable_values: {}
        }))
    };

    router.post(
        route('propoff.admin.events.event-questions.bulkCreateFromTemplates', props.event.id),
        payload,
        {
            onSuccess: () => {
                selectedTemplates.value = [];
                categorySearch.value = '';
                searchPerformed.value = false;
                filteredTemplates.value = [];
            },
            onError: (errors) => {
                console.error('Error importing templates:', errors);
                alert('Error importing templates. Please try again.');
            }
        }
    );
};

// Delete question
const deleteQuestion = (questionId) => {
    if (confirm('Delete this question?')) {
        router.delete(
            route('propoff.admin.events.event-questions.destroy', [props.event.id, questionId]),
            {
                onSuccess: () => {
                    router.visit(route('propoff.admin.events.import-questions', props.event.id));
                }
            }
        );
    }
};

// Delete all questions
const deleteAllQuestions = () => {
    if (confirm(`Delete all ${props.currentQuestions.length} questions? This cannot be undone.`)) {
        router.delete(
            route('propoff.admin.events.event-questions.destroyAll', props.event.id),
            {
                onSuccess: () => {
                    router.visit(route('propoff.admin.events.import-questions', props.event.id));
                }
            }
        );
    }
};

// Format type
const formatType = (type) => {
    if (!type) return 'Unknown';
    return type.split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

// Format date
const formatDate = (date) => {
    if (!date) return 'No date';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
};

// Badge variant for question type
const typeBadgeVariant = (type) => {
    if (!type) return 'default';
    const variants = {
        multiple_choice: 'primary-soft',
        yes_no: 'success-soft',
        numeric: 'warning-soft',
        text: 'default',
        ranked_answers: 'warning-soft',
    };
    return variants[type] || 'default';
};
</script>
