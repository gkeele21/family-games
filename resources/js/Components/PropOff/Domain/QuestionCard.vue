<template>
    <div class="space-y-3">
        <!-- Question Header -->
        <div v-if="showHeader" class="mb-4">
            <!-- Mobile: Stack layout, Desktop: Inline with actions on right -->
            <div class="flex flex-col sm:flex-row sm:items-start gap-2">
                <!-- Question text + inline metadata -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-start gap-2">
                        <!-- Drag Handle -->
                        <div v-if="showDragHandle" class="flex-shrink-0 cursor-move text-muted hover:text-body mt-0.5" title="Drag to reorder">
                            <Icon name="grip-vertical" size="lg" />
                        </div>
                        <span v-if="questionNumber" class="text-lg font-bold text-body flex-shrink-0">
                            {{ questionNumber }}.
                        </span>
                        <p class="text-lg font-semibold text-body">
                            {{ question }}
                            <!-- Base points inline with question text -->
                            <span v-if="points" class="text-sm font-normal text-muted ml-1">({{ points }} {{ points === 1 ? 'pt' : 'pts' }})</span>
                            <!-- Badges inline after points -->
                            <Badge v-if="questionType" :variant="questionTypeBadgeVariant" size="sm" class="ml-2 align-middle">
                                {{ questionType.replace('_', ' ') }}
                            </Badge>
                            <Badge v-if="isVoid" variant="danger" size="sm" class="ml-1 align-middle">Voided</Badge>
                            <Badge v-if="isCustom" variant="warning-soft" size="sm" class="ml-1 align-middle">Custom</Badge>
                        </p>
                    </div>
                </div>

                <!-- Actions (edit button, etc.) - stays on right -->
                <div v-if="$slots.headerActions" class="flex items-center gap-2 flex-shrink-0">
                    <slot name="headerActions"></slot>
                </div>
            </div>
        </div>

        <!-- Options -->
        <div class="space-y-3">
            <label
                v-for="(option, index) in normalizedOptions"
                :key="index"
                :for="`${inputName}-${index}`"
                class="flex items-center justify-between p-3 sm:p-4 border-2 rounded-lg transition-all"
                :class="[
                    getOptionClasses(option),
                    disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
                    getFocusGlowClass()
                ]"
            >
                <!-- Hidden Radio Input for Keyboard Navigation -->
                <input
                    :id="`${inputName}-${index}`"
                    type="radio"
                    :name="inputName"
                    :value="option.value"
                    :checked="isSelected(option)"
                    :disabled="disabled"
                    @change="$emit('update:modelValue', option.value)"
                    class="sr-only"
                />
                <div class="flex items-center flex-1 gap-2 sm:gap-3 flex-wrap">
                    <!-- Result Icon (results mode only, when showResultIcons is true) -->
                    <div v-if="showResults && showResultIcons" class="w-5 flex-shrink-0">
                        <Icon
                            v-if="isCorrect(option) && isSelected(option)"
                            name="check"
                            class="text-success"
                            size="sm"
                        />
                        <Icon
                            v-else-if="isCorrect(option) && !isSelected(option)"
                            name="arrow-right"
                            class="text-success"
                            size="sm"
                        />
                        <Icon
                            v-else-if="!isCorrect(option) && isSelected(option)"
                            name="xmark"
                            class="text-danger"
                            size="sm"
                        />
                    </div>

                    <!-- Custom Radio Button (hidden in results mode) -->
                    <div
                        v-if="!showResults"
                        class="w-[18px] h-[18px] rounded-full border-2 flex-shrink-0 relative transition-colors"
                        :class="[
                            isSelected(option)
                                ? (selectionColor === 'info' ? 'border-info bg-info' : 'border-primary bg-primary')
                                : 'border-border-strong bg-transparent'
                        ]"
                    >
                        <!-- Inner dot when selected -->
                        <div
                            v-if="isSelected(option)"
                            class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-1.5 h-1.5 rounded-full bg-bg"
                        ></div>
                    </div>

                    <!-- Letter Prefix (optional) -->
                    <span v-if="showLetters" class="font-semibold text-muted w-6">
                        {{ getLetter(index) }})
                    </span>

                    <!-- Option Label -->
                    <span :class="[
                        'text-body',
                        showResults && isCorrect(option) ? 'font-semibold text-success' : ''
                    ]">
                        {{ option.label }}
                    </span>

                    <!-- Bonus Points -->
                    <span v-if="option.points && option.points > 0" class="text-xs font-semibold text-warning bg-warning/15 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded whitespace-nowrap flex-shrink-0">
                        +{{ option.points }} bonus {{ option.points === 1 ? 'pt' : 'pts' }}
                    </span>
                </div>

                <!-- Pick Count (shown when we have distribution data - i.e. group is locked) -->
                <div v-if="answerDistribution && getPickCount(option.value) > 0" class="flex-shrink-0">
                    <button
                        type="button"
                        :ref="el => setButtonRef(option.value, el)"
                        @click.prevent.stop="toggleDropdown(option.value)"
                        class="text-xs text-muted hover:text-body transition-colors flex items-center gap-1"
                    >
                        <Icon name="users" size="xs" />
                        {{ getPickCount(option.value) }}
                        <Icon :name="openDropdown === option.value ? 'chevron-up' : 'chevron-down'" size="xs" />
                    </button>

                    <!-- Dropdown with user names (teleported to body to escape stacking context) -->
                    <Teleport to="body">
                        <div
                            v-if="openDropdown === option.value"
                            class="fixed z-[9999] bg-[#1f1f1f] border border-border rounded-lg shadow-xl p-3 min-w-[160px] max-h-[200px] overflow-y-auto"
                            :style="getDropdownPosition(option.value)"
                        >
                            <div class="text-xs font-semibold text-muted mb-2">{{ getPickCount(option.value) }} picked this</div>
                            <ul class="space-y-1">
                                <li v-for="userName in getPickUsers(option.value)" :key="userName" class="text-sm text-body">
                                    {{ userName }}
                                </li>
                            </ul>
                        </div>
                    </Teleport>
                </div>
            </label>
        </div>

        <!-- Error Message -->
        <p v-if="error" class="text-sm text-danger mt-2">
            <Icon name="circle-exclamation" size="xs" class="mr-1" />
            {{ typeof error === 'string' ? error : 'Please select an answer' }}
        </p>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import Icon from '@/Components/Base/Icon.vue';
import Badge from '@/Components/Base/Badge.vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    question: { type: String, default: '' },
    options: { type: Array, default: null },
    points: { type: Number, default: 0 },
    questionNumber: { type: Number, default: null },
    correctAnswer: { type: [String, Number], default: null },
    showResults: { type: Boolean, default: false },
    showHeader: { type: Boolean, default: true },
    showLetters: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    error: { type: [String, Boolean], default: false },
    name: { type: String, default: '' },
    selectionColor: { type: String, default: 'primary' }, // 'primary' or 'info'
    showResultIcons: { type: Boolean, default: true }, // Show check/x/arrow icons in results mode
    selectionBg: { type: Boolean, default: true }, // Show background tint on selection
    showFocusGlow: { type: Boolean, default: true }, // Show focus-within glow on click/tab
    showIncorrectIndicator: { type: Boolean, default: true }, // Show red for wrong selections (false for admin/captain grading)
    // Management view props
    showDragHandle: { type: Boolean, default: false }, // Show drag handle for reordering
    questionType: { type: String, default: null }, // Question type badge (multiple_choice, yes_no, etc.)
    isVoid: { type: Boolean, default: false }, // Show voided badge
    isCustom: { type: Boolean, default: false }, // Show custom badge
    // Answer distribution (for showing who picked what)
    answerDistribution: { type: Object, default: null }, // { 'Option A': { count: 5, users: ['John', 'Jane', ...] }, ... }
});

// Track which option's dropdown is open
const openDropdown = ref(null);

// Store button refs for positioning
const buttonRefs = ref({});

function setButtonRef(optionValue, el) {
    if (el) {
        buttonRefs.value[optionValue] = el;
    }
}

function toggleDropdown(optionValue) {
    if (openDropdown.value === optionValue) {
        openDropdown.value = null;
    } else {
        openDropdown.value = optionValue;
    }
}

function getDropdownPosition(optionValue) {
    const button = buttonRefs.value[optionValue];
    if (!button) return {};

    const rect = button.getBoundingClientRect();
    return {
        top: `${rect.bottom + 4}px`,
        right: `${window.innerWidth - rect.right}px`,
    };
}

function getPickCount(optionValue) {
    if (!props.answerDistribution || !props.answerDistribution[optionValue]) {
        return 0;
    }
    return props.answerDistribution[optionValue].count;
}

function getPickUsers(optionValue) {
    if (!props.answerDistribution || !props.answerDistribution[optionValue]) {
        return [];
    }
    return props.answerDistribution[optionValue].users;
}

// Close dropdown when clicking outside
function handleClickOutside(event) {
    if (openDropdown.value && !event.target.closest('.relative')) {
        openDropdown.value = null;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

// Question type badge variant
const questionTypeBadgeVariant = computed(() => {
    switch (props.questionType) {
        case 'multiple_choice': return 'info-soft';
        case 'yes_no': return 'success-soft';
        case 'numeric': return 'warning-soft';
        default: return 'default';
    }
});

defineEmits(['update:modelValue']);

const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

// Generate unique input name for radio group
const inputName = computed(() => {
    return props.name || `question-${Math.random().toString(36).substr(2, 9)}`;
});

// Normalize options to always have { label, value, points } format
const normalizedOptions = computed(() => {
    if (!props.options) return [];
    return props.options.map((option, index) => {
        if (typeof option === 'string') {
            return { label: option, value: option, points: 0 };
        }
        return {
            label: option.label || option.text || option,
            value: option.value || option.label || option.text || option,
            points: option.points || option.bonus || 0,
        };
    });
});

function getLetter(index) {
    return alphabet.charAt(index);
}

function isSelected(option) {
    return props.modelValue === option.value;
}

function isCorrect(option) {
    return props.correctAnswer !== null && option.value === props.correctAnswer;
}

function getFocusGlowClass() {
    if (!props.showFocusGlow) return '';
    // No focus glow in results mode (controls are disabled)
    if (props.showResults) return '';
    // Use selection color's glow for normal mode
    return props.selectionColor === 'info' ? 'focus-within-glow-info' : 'focus-within-glow';
}

function getOptionClasses(option) {
    const bg = props.selectionBg;
    const glow = props.selectionColor === 'info' ? 'checked-glow-info' : 'checked-glow';

    // Results mode styling - just outlines, dark background
    if (props.showResults) {
        // Admin/captain grading mode: selection blue, saved answer green (when not selected)
        if (!props.showIncorrectIndicator) {
            // Selection always shows blue with glow
            if (isSelected(option)) {
                return props.selectionColor === 'info'
                    ? `border-info ${bg ? 'bg-info/10' : 'bg-surface-inset'} ${glow}`
                    : `border-primary ${bg ? 'bg-primary/10' : 'bg-surface-inset'} checked-glow`;
            }

            // Saved answer (not selected) shows green without glow
            if (isCorrect(option)) {
                return 'border-success bg-surface-inset';
            }

            return 'border-border bg-surface-inset hover:border-border-strong hover:bg-surface-overlay';
        }

        // Player-facing results mode
        if (isSelected(option)) {
            if (isCorrect(option)) {
                return 'border-success bg-surface-inset';
            }
            return 'border-danger bg-surface-inset';
        }
        // Correct answer (not selected) - green outline to show what was right
        if (isCorrect(option)) {
            return 'border-success bg-surface-inset';
        }
        return 'border-border bg-surface-inset';
    }

    // Normal mode styling
    if (isSelected(option)) {
        return props.selectionColor === 'info'
            ? `border-info ${bg ? 'bg-info/10' : 'bg-surface-inset'} ${glow}`
            : `border-primary ${bg ? 'bg-primary/10' : 'bg-surface-inset'} checked-glow`;
    }
    return 'border-border bg-surface-inset hover:border-border-strong hover:bg-surface-overlay';
}
</script>
