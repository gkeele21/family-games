<template>
    <div>
        <FormLabel v-if="label" :html-for="inputId" :required="required">{{ label }}</FormLabel>

        <div class="relative" ref="containerRef">
            <!-- Trigger button -->
            <button
                ref="triggerRef"
                :id="inputId"
                type="button"
                @click="toggleDropdown"
                @keydown.down.prevent="openAndHighlightFirst"
                @keydown.up.prevent="openAndHighlightLast"
                @keydown.enter.prevent="toggleDropdown"
                @keydown.space.prevent="toggleDropdown"
                @keydown.escape="closeDropdown"
                :disabled="disabled"
                :class="[
                    'flex items-center justify-between rounded border transition-all text-left',
                    'focus:outline-none focus-glow',
                    variantClasses,
                    disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
                    sizes[size],
                    shouldBeFullWidth ? 'w-full' : '',
                ]"
            >
                <span :class="[selectedLabel ? 'text-body' : 'text-muted']">
                    {{ selectedLabel || placeholder || 'Select...' }}
                </span>
                <Icon
                    :name="isOpen ? 'chevron-up' : 'chevron-down'"
                    size="sm"
                    class="text-muted ml-2 flex-shrink-0"
                />
            </button>

            <!-- Dropdown panel (teleported to body) -->
            <teleport defer to="#propoff-app">
                <Transition
                    enter-active-class="transition ease-out duration-100"
                    enter-from-class="transform opacity-0 scale-95"
                    enter-to-class="transform opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-75"
                    leave-from-class="transform opacity-100 scale-100"
                    leave-to-class="transform opacity-0 scale-95"
                >
                    <div
                        v-if="isOpen"
                        ref="dropdownRef"
                        class="fixed z-[9999] bg-surface border border-primary rounded-lg shadow-lg overflow-auto"
                        :style="dropdownStyle"
                        @keydown.down.prevent="highlightNext"
                        @keydown.up.prevent="highlightPrev"
                        @keydown.enter.prevent="selectHighlighted"
                        @keydown.escape="closeDropdown"
                        tabindex="-1"
                    >
                        <!-- Empty option -->
                        <div
                            v-if="allowEmpty"
                            @click="selectOption(emptyValue)"
                            @mouseenter="highlightedIndex = -1"
                            :class="[
                                'px-3 py-2 cursor-pointer text-sm transition-colors',
                                highlightedIndex === -1 && modelValue === emptyValue
                                    ? 'bg-primary text-white'
                                    : modelValue === emptyValue
                                        ? 'bg-primary/30 text-primary'
                                        : 'hover:bg-surface-overlay text-muted'
                            ]"
                        >
                            {{ emptyLabel }}
                        </div>

                        <!-- Options list -->
                        <div
                            v-for="(option, index) in normalizedOptions"
                            :key="option.value"
                            @click="selectOption(option.value)"
                            @mouseenter="highlightedIndex = index"
                            :class="[
                                'px-3 py-2 cursor-pointer text-sm transition-colors',
                                highlightedIndex === index
                                    ? 'bg-primary text-white'
                                    : modelValue === option.value
                                        ? 'bg-primary/30 text-primary'
                                        : 'hover:bg-surface-overlay text-body'
                            ]"
                        >
                            {{ option.label }}
                        </div>

                        <!-- No options -->
                        <div v-if="normalizedOptions.length === 0 && !allowEmpty" class="px-3 py-2 text-sm text-muted">
                            No options available
                        </div>
                    </div>
                </Transition>
            </teleport>
        </div>

        <p v-if="error" class="mt-1 text-sm text-danger">{{ error }}</p>
        <p v-else-if="hint" class="mt-1 text-sm text-subtle">{{ hint }}</p>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import Icon from '@/Components/Base/Icon.vue';
import FormLabel from '@/Components/Form/FormLabel.vue';

const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    options: { type: Array, required: true },
    // For simple arrays of objects, specify which fields to use
    valueField: { type: String, default: 'value' },
    labelField: { type: [String, Array], default: 'label' },
    // Nested object support (e.g., option.user.name)
    labelObject: { type: String, default: '' },
    label: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    size: { type: String, default: 'md' },
    variant: { type: String, default: 'default' }, // 'default' | 'outline-light'
    autoWidth: { type: Boolean, default: false },
    allowEmpty: { type: Boolean, default: false },
    emptyValue: { type: [String, Number, null], default: '' },
    emptyLabel: { type: String, default: '-- Select --' },
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const highlightedIndex = ref(-1);
const containerRef = ref(null);
const triggerRef = ref(null);
const dropdownRef = ref(null);
const dropdownPosition = ref({ top: 0, left: 0, width: 0, maxHeight: 240 });

const sizes = {
    sm: 'py-1.5 px-3 text-sm',
    md: 'py-2 px-3 text-base',
    lg: 'py-3 px-3 text-lg',
};

const inputId = computed(() => `select-${Math.random().toString(36).substr(2, 9)}`);

// Determine if full width should be applied
const shouldBeFullWidth = computed(() => !props.autoWidth);

const variantClasses = computed(() => {
    if (props.error) {
        return 'border-danger focus:border-transparent bg-surface-inset';
    }

    switch (props.variant) {
        case 'outline-light':
            return 'bg-transparent text-white border-white focus:border-transparent';
        default:
            return props.disabled
                ? 'bg-surface border-border focus:border-transparent'
                : 'bg-surface-inset border-border focus:border-transparent';
    }
});

const normalizedOptions = computed(() => {
    return props.options.map(option => {
        // If option is a primitive (string/number), use it as both value and label
        if (typeof option !== 'object') {
            return { value: option, label: option };
        }

        // Get the value
        const value = option[props.valueField];

        // Get the label - support nested objects and multiple label fields
        let label;
        const source = props.labelObject ? option[props.labelObject] : option;

        if (Array.isArray(props.labelField)) {
            // Support array of fields to concatenate (e.g., ['first_name', 'last_name'])
            label = props.labelField.map(field => source?.[field]).filter(Boolean).join(' ');
        } else {
            label = source?.[props.labelField];
        }

        return { value, label };
    });
});

const selectedLabel = computed(() => {
    if (props.modelValue === props.emptyValue && props.allowEmpty) {
        return props.emptyLabel;
    }
    const option = normalizedOptions.value.find(o => o.value === props.modelValue);
    return option?.label || '';
});

const dropdownStyle = computed(() => ({
    top: `${dropdownPosition.value.top}px`,
    left: `${dropdownPosition.value.left}px`,
    width: `${dropdownPosition.value.width}px`,
    maxHeight: `${dropdownPosition.value.maxHeight}px`,
}));

function updateDropdownPosition() {
    if (!triggerRef.value) return;
    const rect = triggerRef.value.getBoundingClientRect();
    // Use viewport coordinates directly since we're using fixed positioning
    const gap = 4;
    const viewportPadding = 8;
    const maxPanelHeight = 240; // matches the previous max-h-60 cap
    const panelHeight = Math.min(
        dropdownRef.value?.scrollHeight ?? maxPanelHeight,
        maxPanelHeight,
    );
    const spaceBelow = window.innerHeight - rect.bottom - gap - viewportPadding;
    const spaceAbove = rect.top - gap - viewportPadding;

    // Open downward by default; flip above the trigger when the panel
    // doesn't fit below and there's more room above.
    if (panelHeight > spaceBelow && spaceAbove > spaceBelow) {
        const height = Math.min(panelHeight, spaceAbove);
        dropdownPosition.value = {
            top: rect.top - gap - height,
            left: rect.left,
            width: rect.width,
            maxHeight: height,
        };
    } else {
        dropdownPosition.value = {
            top: rect.bottom + gap,
            left: rect.left,
            width: rect.width,
            maxHeight: Math.min(panelHeight, Math.max(spaceBelow, 100)),
        };
    }
}

function toggleDropdown() {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        // Set highlight to current selection
        const currentIndex = normalizedOptions.value.findIndex(o => o.value === props.modelValue);
        highlightedIndex.value = currentIndex >= 0 ? currentIndex : 0;
        nextTick(() => {
            updateDropdownPosition();
            dropdownRef.value?.focus();
        });
    }
}

function openAndHighlightFirst() {
    if (!isOpen.value) {
        isOpen.value = true;
        highlightedIndex.value = 0;
        nextTick(() => {
            updateDropdownPosition();
            dropdownRef.value?.focus();
        });
    } else {
        highlightNext();
    }
}

function openAndHighlightLast() {
    if (!isOpen.value) {
        isOpen.value = true;
        highlightedIndex.value = normalizedOptions.value.length - 1;
        nextTick(() => {
            updateDropdownPosition();
            dropdownRef.value?.focus();
        });
    } else {
        highlightPrev();
    }
}

function closeDropdown() {
    isOpen.value = false;
    triggerRef.value?.focus();
}

function selectOption(value) {
    const parsedValue = value !== '' && !isNaN(value) && typeof props.modelValue === 'number'
        ? Number(value)
        : value;
    emit('update:modelValue', parsedValue);
    emit('change', parsedValue);
    closeDropdown();
}

function highlightNext() {
    if (normalizedOptions.value.length === 0) return;
    highlightedIndex.value = (highlightedIndex.value + 1) % normalizedOptions.value.length;
}

function highlightPrev() {
    if (normalizedOptions.value.length === 0) return;
    highlightedIndex.value = highlightedIndex.value <= 0
        ? normalizedOptions.value.length - 1
        : highlightedIndex.value - 1;
}

function selectHighlighted() {
    if (highlightedIndex.value >= 0 && highlightedIndex.value < normalizedOptions.value.length) {
        selectOption(normalizedOptions.value[highlightedIndex.value].value);
    }
}

// Close on outside click
function handleClickOutside(event) {
    const clickedInContainer = containerRef.value?.contains(event.target);
    const clickedInDropdown = dropdownRef.value?.contains(event.target);
    if (!clickedInContainer && !clickedInDropdown) {
        isOpen.value = false;
    }
}

// Close on page scroll, but not when scrolling within the dropdown itself
function handleScroll(event) {
    if (isOpen.value && !dropdownRef.value?.contains(event.target)) {
        isOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    window.addEventListener('scroll', handleScroll, true);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('scroll', handleScroll, true);
});
</script>
