<script setup>
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import PageHeader from '@/Components/Base/PageHeader.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Button from '@/Components/Base/Button.vue';
import Icon from '@/Components/Base/Icon.vue';
import TextField from '@/Components/Form/TextField.vue';
import FormLabel from '@/Components/Form/FormLabel.vue';
import NumberInput from '@/Components/Form/NumberInput.vue';
import Confirm from '@/Components/Feedback/Confirm.vue';

const props = defineProps({
    template: {
        type: Object,
        default: null,
    },
});

const isEditing = computed(() => !!props.template);
const showDeleteConfirm = ref(false);

// Normalize options to new format if needed (backward compatibility)
const normalizeOptions = (options) => {
    if (!options) return [{ label: '', points: 0 }, { label: '', points: 0 }];
    if (typeof options === 'string') {
        try {
            options = JSON.parse(options);
        } catch (e) {
            return [{ label: '', points: 0 }, { label: '', points: 0 }];
        }
    }
    if (!Array.isArray(options) || options.length === 0) {
        return [{ label: '', points: 0 }, { label: '', points: 0 }];
    }
    if (typeof options[0] === 'object' && options[0].label !== undefined) {
        return options;
    }
    return options.map(opt => ({
        label: opt,
        points: 0
    }));
};

const form = useForm({
    title: props.template?.title || '',
    category: props.template?.category || '',
    question_type: props.template?.question_type || 'multiple_choice',
    question_text: props.template?.question_text || '',
    variables: props.template?.variables || [],
    default_options: normalizeOptions(props.template?.default_options),
    default_points: props.template?.default_points || 10,
    template_answers: props.template?.template_answers?.map(a => ({
        answer_text: a.answer_text,
        display_order: a.display_order
    })) || [],
});

// Extract variables from question text and options automatically
const extractedVariables = computed(() => {
    const variables = new Set();

    // Extract from question text
    const questionText = form.question_text || '';
    const questionMatches = questionText.match(/\{(\w+)\}/g) || [];
    for (const match of questionMatches) {
        variables.add(match.slice(1, -1)); // Remove { and }
    }

    // Extract from option labels
    const options = form.default_options || [];
    for (const option of options) {
        if (option?.label) {
            const optionMatches = option.label.match(/\{(\w+)\}/g) || [];
            for (const match of optionMatches) {
                variables.add(match.slice(1, -1));
            }
        }
    }

    return Array.from(variables);
});

// Sync form.variables with extracted variables
watch(extractedVariables, (newVars) => {
    form.variables = newVars;
}, { immediate: true });

const addOption = () => {
    form.default_options.push({ label: '', points: 0 });
};

const removeOption = (index) => {
    if (form.default_options.length > 2) {
        form.default_options.splice(index, 1);
    }
};

// Template Answers functions
const addTemplateAnswer = () => {
    const nextOrder = form.template_answers.length + 1;
    if (nextOrder <= 7) {
        form.template_answers.push({
            answer_text: '',
            display_order: nextOrder,
        });
    }
};

const removeTemplateAnswer = (index) => {
    form.template_answers.splice(index, 1);
    form.template_answers.forEach((answer, idx) => {
        answer.display_order = idx + 1;
    });
};

const moveAnswerUp = (index) => {
    if (index > 0) {
        const temp = form.template_answers[index];
        form.template_answers[index] = form.template_answers[index - 1];
        form.template_answers[index - 1] = temp;
        form.template_answers.forEach((answer, idx) => {
            answer.display_order = idx + 1;
        });
    }
};

const moveAnswerDown = (index) => {
    if (index < form.template_answers.length - 1) {
        const temp = form.template_answers[index];
        form.template_answers[index] = form.template_answers[index + 1];
        form.template_answers[index + 1] = temp;
        form.template_answers.forEach((answer, idx) => {
            answer.display_order = idx + 1;
        });
    }
};

const categoryTags = computed(() => {
    if (!form.category) return [];
    return form.category
        .split(',')
        .map(c => c.trim())
        .filter(c => c.length > 0);
});

const submit = () => {
    if (isEditing.value) {
        form.put(route('propoff.admin.question-templates.update', props.template.id));
    } else {
        form.post(route('propoff.admin.question-templates.store'));
    }
};

const deleteTemplate = () => {
    router.delete(route('propoff.admin.question-templates.destroy', props.template.id));
};
</script>

<template>
    <Head :title="isEditing ? 'Edit Question Template' : 'Create Question Template'" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                :title="isEditing ? 'Edit Template' : 'Create Template'"
                :subtitle="isEditing ? template.title : 'Create a new reusable question template'"
                :crumbs="[
                    { label: 'Templates', href: route('propoff.admin.question-templates.index') },
                    { label: isEditing ? 'Edit' : 'Create' }
                ]"
            >
                <template #actions>
                    <Link :href="route('propoff.admin.question-templates.index')">
                        <Button variant="outline" size="sm">Cancel</Button>
                    </Link>
                    <Button
                        v-if="isEditing"
                        variant="danger"
                        size="sm"
                        @click="showDeleteConfirm = true"
                    >
                        Delete
                    </Button>
                    <Button
                        variant="primary"
                        size="sm"
                        @click="submit"
                        :disabled="form.processing"
                    >
                        {{ isEditing ? 'Save' : 'Create Template' }}
                    </Button>
                </template>
            </PageHeader>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg border border-border">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <!-- Template Name -->
                        <TextField
                            v-model="form.title"
                            label="Template Name"
                            :error="form.errors.title"
                            required
                        />

                        <!-- Category -->
                        <div>
                            <TextField
                                v-model="form.category"
                                label="Categories (Optional)"
                                :error="form.errors.category"
                                placeholder="e.g., football,nfl,sports"
                            />
                            <p class="mt-1 text-sm text-muted">
                                Enter one or more categories separated by commas
                            </p>
                            <div v-if="categoryTags.length > 0" class="mt-2 flex gap-2 flex-wrap">
                                <span
                                    v-for="tag in categoryTags"
                                    :key="tag"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary/15 text-primary"
                                >
                                    {{ tag }}
                                </span>
                            </div>
                        </div>

                        <!-- Question Type -->
                        <div>
                            <FormLabel required>Question Type</FormLabel>
                            <select
                                v-model="form.question_type"
                                class="mt-1 block w-full bg-surface-inset border border-border text-body focus:border-primary focus:ring-1 focus:ring-primary rounded-lg px-4 py-2"
                                required
                            >
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="yes_no">Yes/No</option>
                                <option value="numeric">Numeric</option>
                                <option value="text">Text</option>
                                <option value="ranked_answers">Ranked Answers (AmericaSays)</option>
                            </select>
                            <p v-if="form.errors.question_type" class="mt-1 text-sm text-danger">{{ form.errors.question_type }}</p>
                        </div>

                        <!-- Question Text -->
                        <TextField
                            v-model="form.question_text"
                            label="Question Text"
                            :error="form.errors.question_text"
                            required
                            multiline
                            :rows="4"
                            placeholder="Use {variable} syntax for dynamic content"
                            hint="Use curly braces for variables, e.g., {team1}, {player1}"
                        />

                        <!-- Base Points -->
                        <NumberInput
                            v-model="form.default_points"
                            label="Base Points"
                            :error="form.errors.default_points"
                            :min="1"
                            required
                            hint="Points awarded for answering correctly (+ any option bonus)"
                        />

                        <!-- Options (for multiple choice) -->
                        <div v-if="form.question_type === 'multiple_choice'" class="space-y-3">
                            <FormLabel required>Answer Options</FormLabel>
                            <p class="text-sm text-muted mb-3">
                                Set the option labels and optional bonus points. Options can use {'{'}variable{'}'} syntax.
                            </p>

                            <div class="space-y-2">
                                <div
                                    v-for="(option, index) in form.default_options"
                                    :key="index"
                                    class="flex items-start gap-2"
                                >
                                    <!-- Option Letter -->
                                    <div class="flex-shrink-0 w-8 h-8 flex items-center justify-center bg-primary/15 text-primary font-medium rounded-full text-sm mt-2">
                                        {{ String.fromCharCode(65 + index) }}
                                    </div>

                                    <!-- Option Input Container -->
                                    <div class="flex-1 flex items-start gap-2">
                                        <!-- Option Text Input -->
                                        <div class="flex-1">
                                            <TextField
                                                v-model="form.default_options[index].label"
                                                :placeholder="`Option ${String.fromCharCode(65 + index)}`"
                                            />
                                        </div>

                                        <!-- Bonus Input Group -->
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            <label class="text-xs text-subtle whitespace-nowrap">Bonus:</label>
                                            <NumberInput
                                                v-model="form.default_options[index].points"
                                                :min="0"
                                                :step="1"
                                                placeholder="0"
                                                class="w-16"
                                            />
                                        </div>
                                    </div>

                                    <!-- Remove Button -->
                                    <Button
                                        v-if="form.default_options.length > 2"
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        @click="removeOption(index)"
                                        class="mt-2"
                                    >
                                        <Icon name="trash" class="text-danger" size="sm" />
                                    </Button>
                                </div>
                            </div>

                            <Button type="button" variant="muted" size="sm" @click="addOption">
                                <Icon name="plus" class="mr-2" size="sm" />
                                Add Option
                            </Button>
                        </div>

                        <!-- Template Answers (for Ranked Answers type) -->
                        <div v-if="form.question_type === 'ranked_answers'" class="space-y-3">
                            <div class="flex items-center justify-between">
                                <FormLabel required>Ranked Answers</FormLabel>
                                <span class="text-xs text-muted">Up to 7 answers</span>
                            </div>
                            <p class="text-sm text-muted">
                                Add ranked answers for this AmericaSays-style question.
                            </p>

                            <div v-if="form.template_answers.length > 0" class="space-y-2">
                                <div
                                    v-for="(answer, index) in form.template_answers"
                                    :key="index"
                                    class="flex items-start gap-2"
                                >
                                    <!-- Rank Number -->
                                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-warning/15 text-warning flex items-center justify-center font-bold text-sm mt-2">
                                        #{{ answer.display_order }}
                                    </div>

                                    <!-- Answer Input -->
                                    <div class="flex-1">
                                        <TextField
                                            v-model="answer.answer_text"
                                            placeholder="Enter answer..."
                                        />
                                    </div>

                                    <!-- Reorder Buttons -->
                                    <div class="flex flex-col gap-1 mt-1">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="xs"
                                            @click="moveAnswerUp(index)"
                                            :disabled="index === 0"
                                        >
                                            <Icon name="chevron-up" size="sm" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="xs"
                                            @click="moveAnswerDown(index)"
                                            :disabled="index === form.template_answers.length - 1"
                                        >
                                            <Icon name="chevron-down" size="sm" />
                                        </Button>
                                    </div>

                                    <!-- Remove Button -->
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        @click="removeTemplateAnswer(index)"
                                        class="mt-2"
                                    >
                                        <Icon name="trash" class="text-danger" size="sm" />
                                    </Button>
                                </div>
                            </div>

                            <Button
                                v-if="form.template_answers.length < 7"
                                type="button"
                                variant="muted"
                                size="sm"
                                @click="addTemplateAnswer"
                            >
                                <Icon name="plus" class="mr-2" size="sm" />
                                Add Answer ({{ form.template_answers.length }}/7)
                            </Button>

                            <div v-if="form.template_answers.length > 0" class="bg-primary/10 border border-primary/30 rounded-lg p-3">
                                <p class="text-xs text-primary">
                                    <strong>Tip:</strong> Answer #1 should be the most popular/common response, #7 the least popular.
                                </p>
                            </div>
                        </div>

                        <!-- Detected Variables (auto-extracted) -->
                        <div v-if="extractedVariables.length > 0" class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-3">
                                <Icon name="code" class="text-blue-400" />
                                <h4 class="text-sm font-semibold text-blue-400">Detected Variables</h4>
                            </div>
                            <div class="flex flex-wrap gap-2 mb-2">
                                <span
                                    v-for="variable in extractedVariables"
                                    :key="variable"
                                    class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-mono bg-blue-500/20 text-blue-300 border border-blue-500/30"
                                >
                                    {{ '{' + variable + '}' }}
                                </span>
                            </div>
                            <p class="text-xs text-blue-300/70">
                                Variables are automatically detected from your question text and options.
                            </p>
                        </div>

                        <!-- Preview -->
                        <div class="bg-primary/10 border border-primary/30 rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-primary mb-2">Preview</h4>
                            <p class="text-sm text-body mb-2">{{ form.question_text || 'Your question will appear here...' }}</p>
                            <div v-if="extractedVariables.length > 0" class="text-xs text-muted">
                                <strong>Variables:</strong>
                                <span v-for="(variable, index) in extractedVariables" :key="variable" class="ml-1 font-mono">
                                    {{ '{' + variable + '}' }}{{ index < extractedVariables.length - 1 ? ',' : '' }}
                                </span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation -->
        <Confirm
            :show="showDeleteConfirm"
            title="Delete Template?"
            message="This will permanently delete this question template. This action cannot be undone."
            confirm-text="Delete"
            variant="danger"
            @confirm="deleteTemplate"
            @close="showDeleteConfirm = false"
        />
    </AuthenticatedLayout>
</template>
