<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import Button from '@/Components/Base/Button.vue';
import Badge from '@/Components/Base/Badge.vue';
import Card from '@/Components/Base/Card.vue';
import Icon from '@/Components/Base/Icon.vue';
import PageHeader from '@/Components/Base/PageHeader.vue';
import QuestionCard from '@/Components/PropOff/Domain/QuestionCard.vue';
import QuestionModal from '@/Components/PropOff/Domain/QuestionModal.vue';
import Confirm from '@/Components/Feedback/Confirm.vue';
import Toast from '@/Components/Feedback/Toast.vue';
import StatTile from '@/Components/Base/StatTile.vue';
import Checkbox from '@/Components/Form/Checkbox.vue';

const props = defineProps({
    group: Object,
    stats: Object,
    questions: {
        type: Array,
        default: () => [],
    },
    isMember: Boolean,
    isCaptain: Boolean,
    isManager: Boolean,
    recentEntries: Array,
});

// State for answer selection and toast
const selectedAnswers = ref({});
const syncToAdmin = ref({}); // Track sync checkbox per question
const savingQuestionId = ref(null); // Track which question is being saved
const toastMessage = ref('');
const showToast = ref(false);
const draggedQuestion = ref(null);
let scrollInterval = null;

// Question modal state
const showAddEditModal = ref(false);
const editingQuestion = ref(null);
const showDeleteConfirm = ref(false);
const questionToDelete = ref(null);

// Open modal for adding new question
const openAddModal = () => {
    editingQuestion.value = null;
    showAddEditModal.value = true;
};

// Open modal for editing question
const openEditModal = (question) => {
    editingQuestion.value = question;
    showAddEditModal.value = true;
};

// Close modal
const closeModal = () => {
    showAddEditModal.value = false;
    editingQuestion.value = null;
};

// Handle delete from modal
const handleDeleteFromModal = (question) => {
    showAddEditModal.value = false;
    editingQuestion.value = null;
    questionToDelete.value = question;
    showDeleteConfirm.value = true;
};

// Execute delete
const deleteQuestion = () => {
    if (!questionToDelete.value) return;

    router.delete(
        route('propoff.groups.questions.destroy', [props.group.id, questionToDelete.value.id]),
        {
            onSuccess: () => {
                showToastMessage('Question deleted successfully');
                questionToDelete.value = null;
                showDeleteConfirm.value = false;
            },
        }
    );
};

// Format date helper
const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

// Answer selection handlers
const selectAnswer = (questionId, answerValue) => {
    selectedAnswers.value[questionId] = answerValue;
    // Initialize sync checkbox - default checked for managers, unchecked for others
    if (syncToAdmin.value[questionId] === undefined) {
        const question = props.questions.find(q => q.id === questionId);
        syncToAdmin.value[questionId] = props.isManager && !!question?.event_question_id;
    }
};

const hasSelectedAnswer = (questionId) => {
    return selectedAnswers.value[questionId] !== undefined;
};

const saveAnswer = (question) => {
    const answer = selectedAnswers.value[question.id];
    if (!answer || savingQuestionId.value === question.id) return;

    savingQuestionId.value = question.id;
    const willSync = syncToAdmin.value[question.id] ?? false;

    router.post(
        route('propoff.groups.grading.setAnswer', [props.group.id, question.id]),
        {
            correct_answer: answer,
            sync_to_admin: willSync,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showToastMessage('Answer saved');
                delete selectedAnswers.value[question.id];
                savingQuestionId.value = null;

                // Scroll to the next unanswered question
                setTimeout(() => {
                    const currentIndex = props.questions.findIndex(q => q.id === question.id);
                    const nextUnanswered = props.questions.find((q, i) => i > currentIndex && !q.correct_answer);

                    if (nextUnanswered) {
                        const element = document.getElementById(`question-${nextUnanswered.id}`);
                        if (element) {
                            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }
                }, 100);
            },
            onError: () => {
                showToastMessage('Error saving answer');
                savingQuestionId.value = null;
            },
        }
    );
};

// Toggle void for question
const toggleVoid = (question) => {
    router.post(
        route('propoff.groups.grading.toggleVoid', [props.group.id, question.id]),
        {},
        {
            onSuccess: () => {
                showToastMessage(question.is_void ? 'Question unvoided' : 'Question voided');
            },
        }
    );
};

// Drag and drop handlers
const dropTarget = ref(null);
const dropPosition = ref(null); // 'before' or 'after'

const handleDragStart = (question) => {
    draggedQuestion.value = question;
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
    draggedQuestion.value = null;
    dropTarget.value = null;
    dropPosition.value = null;
};

const handleDragOver = (e, question) => {
    e.preventDefault();
    if (!draggedQuestion.value || draggedQuestion.value.id === question.id) {
        dropTarget.value = null;
        dropPosition.value = null;
        return;
    }

    // Determine if dropping before or after based on mouse position
    const rect = e.currentTarget.getBoundingClientRect();
    const midpoint = rect.top + rect.height / 2;
    dropTarget.value = question.id;
    dropPosition.value = e.clientY < midpoint ? 'before' : 'after';
};

const handleDragLeave = (e) => {
    // Only clear if leaving the element entirely (not entering a child)
    if (!e.currentTarget.contains(e.relatedTarget)) {
        dropTarget.value = null;
        dropPosition.value = null;
    }
};

const recentlyDroppedId = ref(null);

const handleDrop = (targetQuestion) => {
    // Store values before clearing
    const currentDropPosition = dropPosition.value;
    const droppedQuestionId = draggedQuestion.value?.id;

    // Clear visual indicators
    dropTarget.value = null;
    dropPosition.value = null;

    if (!draggedQuestion.value || draggedQuestion.value.id === targetQuestion.id) {
        return;
    }

    // Calculate new order based on drop position
    let newOrder = targetQuestion.display_order;
    if (currentDropPosition === 'after') {
        newOrder = targetQuestion.display_order + 1;
    }

    router.post(
        route('propoff.groups.questions.reorder', props.group.id),
        {
            question_id: draggedQuestion.value.id,
            new_order: newOrder,
        },
        {
            preserveScroll: false,
            onSuccess: () => {
                showToastMessage('Questions reordered');
                // Mark the dropped question for highlight effect
                recentlyDroppedId.value = droppedQuestionId;
                // Scroll to the dropped question after page refresh settles
                setTimeout(() => {
                    const element = document.getElementById(`question-${droppedQuestionId}`);
                    if (element) {
                        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    // Clear highlight after animation
                    setTimeout(() => {
                        recentlyDroppedId.value = null;
                    }, 1500);
                }, 300);
            },
        }
    );

    draggedQuestion.value = null;
};

// Toast helper
const showToastMessage = (message) => {
    toastMessage.value = message;
    showToast.value = true;
    setTimeout(() => {
        showToast.value = false;
    }, 3000);
};

// Filter for unanswered questions
const showUnansweredOnly = ref(false);

const filteredQuestions = computed(() => {
    if (!showUnansweredOnly.value) {
        return props.questions;
    }
    return props.questions.filter(q => !q.correct_answer);
});

</script>

<template>
    <Head :title="`Manage Questions - ${group.name}`" />

    <AuthenticatedLayout>
        <!-- Page Header (Captain View Only) -->
        <template v-if="isCaptain" #header>
            <PageHeader
                title="Manage Questions"
                subtitle="Add, edit, or remove questions. Set correct answers to calculate scores."
                :crumbs="[
                    { label: 'Home', href: route('propoff.play.hub', { code: group.code }) },
                    { label: 'Questions' }
                ]"
            >
                <template #actions>
                    <Link v-if="group.is_locked" :href="route('propoff.play.game', { code: group.code })">
                        <Button variant="accent" size="sm" icon="eye">
                            View My Answers
                        </Button>
                    </Link>
                    <Link :href="route('propoff.play.leaderboard', { code: group.code })">
                        <Button variant="primary" size="sm" icon="trophy">
                            Leaderboard
                        </Button>
                    </Link>
                </template>
            </PageHeader>
        </template>

        <!-- Captain View Content -->
        <template v-if="isCaptain">
            <div class="max-w-7xl mx-auto px-6 py-8">
                <!-- Stats Row (4 tiles) -->
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <StatTile :value="stats?.total_questions || 0" label="Questions" color="primary" />
                    <StatTile :value="stats?.answered_questions || 0" label="Scored" color="warning" />
                    <StatTile :value="stats?.total_points || 0" label="Max Possible" color="neutral" />
                </div>

                <!-- Grading Not Available Notice -->
                <div v-if="group.grading_source === 'captain' && !group.is_locked" class="mb-6 bg-warning/10 border border-warning/30 rounded-lg p-4 flex items-center gap-3">
                    <Icon name="clock" class="text-warning" size="lg" />
                    <div>
                        <p class="font-semibold text-body">Grading Not Available Yet</p>
                        <p class="text-sm text-muted">You can set correct answers after the entry cutoff has passed. Members can still submit their picks until then.</p>
                    </div>
                </div>

                <!-- Grading Available Notice -->
                <div v-if="group.grading_source === 'captain' && group.is_locked" class="mb-6 bg-info/10 border border-info/30 rounded-lg p-4 flex items-center gap-3">
                    <Icon name="circle-info" class="text-info" size="lg" />
                    <div>
                        <p class="font-semibold text-body">Ready to Grade</p>
                        <p class="text-sm text-muted">Click on the correct answer for each question to set it. Scores will be calculated automatically.</p>
                    </div>
                </div>

                <!-- Questions Section -->
                <Card :header-padding="false" header-bg-class="bg-surface-header">
                    <template #header>
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <h2 class="text-xl font-bold text-body">Questions</h2>
                                    <Badge variant="primary-soft" size="sm">{{ filteredQuestions?.length || 0 }}</Badge>
                                    <span v-if="showUnansweredOnly && filteredQuestions.length !== questions.length" class="text-xs text-muted">
                                        of {{ questions.length }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <button
                                        v-if="group.is_locked"
                                        @click="showUnansweredOnly = !showUnansweredOnly"
                                        class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg transition-colors"
                                        :class="showUnansweredOnly
                                            ? 'bg-primary text-white'
                                            : 'bg-surface-elevated text-muted hover:text-body'"
                                    >
                                        <Icon name="filter" size="sm" />
                                        Unanswered
                                    </button>
                                    <Button variant="primary" size="sm" @click="openAddModal">
                                        <Icon name="plus" class="mr-2" size="sm" />
                                        Add Question
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Questions List -->
                    <div class="space-y-4 p-6">
                        <div
                            v-for="question in filteredQuestions"
                            :key="question.id"
                            :id="`question-${question.id}`"
                            class="relative"
                        >
                            <!-- Drop indicator - before -->
                            <div
                                v-if="dropTarget === question.id && dropPosition === 'before'"
                                class="absolute -top-2 left-0 right-0 h-1 bg-primary rounded-full z-10"
                            />

                            <!-- Question Card -->
                            <div
                                class="bg-surface-elevated border border-border border-l-4 border-l-[#f5f3ef] rounded-lg p-6 transition-all"
                                :class="{
                                    'opacity-50': draggedQuestion?.id === question.id,
                                    'border-primary/50': dropTarget === question.id,
                                    'ring-2 ring-primary ring-offset-2 ring-offset-surface': recentlyDroppedId === question.id,
                                }"
                                draggable="true"
                                @dragstart="handleDragStart(question)"
                                @drag="handleDrag"
                                @dragend="handleDragEnd"
                                @dragover="handleDragOver($event, question)"
                                @dragleave="handleDragLeave"
                                @drop="handleDrop(question)"
                            >
                            <QuestionCard
                                :model-value="selectedAnswers[question.id]"
                                @update:model-value="selectAnswer(question.id, $event)"
                                :question="question.question_text"
                                :options="question.options"
                                :points="question.points"
                                :question-number="question.display_order"
                                :correct-answer="question.correct_answer"
                                :show-results="!!question.correct_answer"
                                :show-header="true"
                                :disabled="group.grading_source !== 'captain' || !group.is_locked"
                                selection-color="info"
                                :selection-bg="false"
                                :show-focus-glow="false"
                                :show-result-icons="false"
                                :show-drag-handle="true"
                                :question-type="question.question_type"
                                :is-void="question.is_void"
                                :is-custom="question.is_custom"
                                :show-incorrect-indicator="false"
                            >
                                <template #headerActions>
                                    <Button variant="ghost" size="sm" @click="openEditModal(question)">
                                        Edit
                                    </Button>
                                    <Button
                                        v-if="group.grading_source === 'captain' && question.correct_answer && group.is_locked"
                                        variant="ghost"
                                        size="sm"
                                        class="!text-warning"
                                        @click="toggleVoid(question)"
                                    >
                                        {{ question.is_void ? 'Unvoid' : 'Void' }}
                                    </Button>
                                </template>
                            </QuestionCard>

                            <!-- Save Answer Section (captain-graded only, requires locked group) -->
                            <div v-if="group.grading_source === 'captain' && group.is_locked && hasSelectedAnswer(question.id) && savingQuestionId !== question.id" class="flex items-center justify-between gap-4 mt-4">
                                    <!-- Sync to Admin Checkbox (only for managers with event-linked questions) -->
                                    <div v-if="isManager && question.event_question_id">
                                        <Checkbox
                                            v-model="syncToAdmin[question.id]"
                                            label="Also set for admin grading"
                                        />
                                    </div>
                                    <div v-else></div>
                                    <Button variant="secondary" @click="saveAnswer(question)">
                                        Save Answer
                                    </Button>
                                </div>

                                <!-- Admin-graded indicator -->
                                <div v-if="group.grading_source === 'admin' && !question.correct_answer" class="text-sm text-muted italic">
                                    Waiting for admin to set answer
                                </div>
                            </div>

                            <!-- Drop indicator - after -->
                            <div
                                v-if="dropTarget === question.id && dropPosition === 'after'"
                                class="absolute -bottom-2 left-0 right-0 h-1 bg-primary rounded-full z-10"
                            />
                        </div>

                        <!-- Empty State - No questions at all -->
                        <div v-if="!questions || questions.length === 0" class="text-center py-12 text-muted">
                            <Icon name="circle-question" size="3x" class="mb-4 text-subtle" />
                            <p class="text-lg mb-2">No questions yet</p>
                            <p class="text-sm mb-4">Add questions for your group members to answer</p>
                            <Button variant="primary" @click="openAddModal">Add Question</Button>
                        </div>

                        <!-- Empty State - All questions answered (when filtering) -->
                        <div v-else-if="filteredQuestions.length === 0 && showUnansweredOnly" class="text-center py-12 text-muted">
                            <Icon name="circle-check" size="3x" class="mb-4 text-success" />
                            <p class="text-lg mb-2">All questions answered</p>
                            <p class="text-sm mb-4">You've set answers for all {{ questions.length }} questions</p>
                            <button
                                @click="showUnansweredOnly = false"
                                class="text-primary hover:text-primary-hover transition-colors"
                            >
                                Show all questions
                            </button>
                        </div>
                    </div>
                </Card>
            </div>
        </template>

        <!-- Member View (non-captain) -->
        <template v-else>
            <div class="py-12">
                <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <!-- Group Info Card -->
                    <Card>
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center">
                                <Icon name="users" class="text-primary" size="lg" />
                            </div>
                            <div class="flex-1">
                                <h1 class="text-2xl font-bold text-body">{{ group.name }}</h1>
                                <p v-if="group.event?.name" class="text-muted mt-1">
                                    Event: {{ group.event.name }}
                                </p>
                                <p v-if="group.description" class="text-sm text-muted mt-2">
                                    {{ group.description }}
                                </p>
                            </div>
                            <div v-if="isMember" class="flex gap-2">
                                <Link :href="route('propoff.groups.leave', group.id)" method="post" as="button">
                                    <Button variant="ghost" size="sm" class="!text-danger">
                                        Leave Group
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </Card>

                    <!-- Recent Entries -->
                    <Card v-if="recentEntries && recentEntries.length > 0" title="Recent Entries">
                        <div class="space-y-3">
                            <div
                                v-for="entry in recentEntries"
                                :key="entry.id"
                                class="flex items-center justify-between p-4 border border-border rounded-lg"
                            >
                                <div>
                                    <div class="font-medium text-body">{{ entry.user?.name }}</div>
                                    <div class="text-sm text-muted">{{ entry.event?.name }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold"
                                        :class="{
                                            'text-success': entry.percentage >= 80,
                                            'text-warning': entry.percentage >= 60 && entry.percentage < 80,
                                            'text-danger': entry.percentage < 60,
                                        }"
                                    >
                                        {{ Math.round(entry.percentage || 0) }}%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>
        </template>

        <!-- Toast Notification -->
        <Toast
            :show="showToast"
            :message="toastMessage"
            @close="showToast = false"
        />

        <!-- Add/Edit Question Modal -->
        <QuestionModal
            :show="showAddEditModal"
            :question="editingQuestion"
            context="group"
            :group-id="group.id"
            @close="closeModal"
            @success="showToastMessage('Question saved successfully')"
            @delete="handleDeleteFromModal"
        />

        <!-- Delete Confirmation Modal -->
        <Confirm
            :show="showDeleteConfirm"
            title="Delete Question?"
            message="This action cannot be undone. The question will be removed from the group."
            variant="danger"
            icon="triangle-exclamation"
            @confirm="deleteQuestion"
            @close="showDeleteConfirm = false"
        />
    </AuthenticatedLayout>
</template>
