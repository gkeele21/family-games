<template>
    <div>
        <FormLabel v-if="label" :html-for="inputId" :required="required">{{ label }}</FormLabel>

        <div class="relative" ref="containerRef">
            <div class="relative">
                <input
                    :id="inputId"
                    ref="inputRef"
                    type="text"
                    v-model="searchQuery"
                    @input="onInput"
                    @focus="onFocus"
                    @blur="onBlur"
                    @keydown.down.prevent="highlightNext"
                    @keydown.up.prevent="highlightPrev"
                    @keydown.enter.prevent="selectHighlighted"
                    @keydown.escape="closeDropdown"
                    :placeholder="placeholder"
                    :disabled="disabled"
                    :required="required"
                    :aria-describedby="descriptionId"
                    :aria-invalid="!!error"
                    :aria-expanded="isOpen"
                    :aria-controls="listboxId"
                    :aria-activedescendant="highlightedIndex >= 0 ? `${listboxId}-option-${highlightedIndex}` : undefined"
                    role="combobox"
                    aria-autocomplete="list"
                    autocomplete="off"
                    :class="[
                        'w-full rounded border transition-all',
                        'focus:outline-none focus-glow',
                        'bg-surface-elevated text-body placeholder:text-subtle',
                        error
                            ? 'border-danger focus:border-transparent'
                            : 'border-border focus:border-transparent',
                        disabled ? 'opacity-50 cursor-not-allowed' : '',
                        sizes[size],
                        'pr-10'
                    ]"
                />

                <!-- Clear button -->
                <button
                    v-if="selectedItem && clearable && !disabled"
                    type="button"
                    @click.stop="clearSelection"
                    class="absolute inset-y-0 right-8 flex items-center text-muted hover:text-body transition-colors"
                    aria-label="Clear selection"
                >
                    <Icon name="times" size="sm" />
                </button>

                <!-- Status icon -->
                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                    <Icon v-if="isLoading" name="spinner" class="animate-spin text-muted" size="sm" />
                    <Icon v-else name="search" class="text-muted" size="sm" />
                </div>
            </div>

            <!-- Dropdown -->
            <div
                v-if="isOpen && (filteredOptions.length > 0 || isLoading || showMinCharsMessage)"
                ref="dropdownRef"
                :id="listboxId"
                role="listbox"
                class="absolute top-full left-0 right-0 mt-1 z-50 bg-surface-elevated border border-border rounded shadow-lg max-h-60 overflow-auto"
            >
                        <!-- Loading state -->
                        <div v-if="isLoading" class="px-4 py-3 text-sm text-muted">
                            <Icon name="spinner" class="animate-spin mr-2" size="sm" />
                            Searching...
                        </div>

                        <!-- Min chars message -->
                        <div v-else-if="showMinCharsMessage" class="px-4 py-3 text-sm text-muted">
                            Type at least {{ minChars }} characters to search...
                        </div>

                        <!-- No results -->
                        <div v-else-if="filteredOptions.length === 0 && searchQuery.length >= minChars" class="px-4 py-3 text-sm text-muted">
                            {{ noResultsText }}
                        </div>

                        <!-- Options list -->
                        <template v-else>
                            <div
                                v-for="(option, index) in filteredOptions"
                                :key="getOptionValue(option)"
                                :id="`${listboxId}-option-${index}`"
                                role="option"
                                :aria-selected="highlightedIndex === index"
                                @mousedown.prevent="selectOption(option)"
                                @mouseenter="highlightedIndex = index"
                                :class="[
                                    'px-4 py-2 cursor-pointer text-sm transition-colors',
                                    highlightedIndex === index
                                        ? 'bg-primary text-white'
                                        : 'hover:bg-surface text-body'
                                ]"
                            >
                                <slot name="option" :option="option" :highlighted="highlightedIndex === index">
                                    <div class="font-medium">{{ getOptionLabel(option) }}</div>
                                    <div
                                        v-if="getOptionDescription(option)"
                                        class="text-xs"
                                        :class="highlightedIndex === index ? 'text-white opacity-80' : 'text-muted'"
                                    >
                                        {{ getOptionDescription(option) }}
                                    </div>
                                </slot>
                            </div>
                        </template>
            </div>
        </div>

        <p v-if="error" :id="descriptionId" class="mt-1 text-sm text-danger">{{ error }}</p>
        <p v-else-if="hint" :id="descriptionId" class="mt-1 text-sm text-subtle">{{ hint }}</p>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import Icon from '@/Components/Base/Icon.vue';
import FormLabel from '@/Components/Form/FormLabel.vue';

const props = defineProps({
    modelValue: { type: [String, Number, Object, null], default: null },
    options: { type: Array, default: () => [] },
    label: { type: String, default: '' },
    placeholder: { type: String, default: 'Search...' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    size: { type: String, default: 'md' },
    clearable: { type: Boolean, default: true },
    minChars: { type: Number, default: 0 },
    noResultsText: { type: String, default: 'No results found' },
    // For async search
    searchFn: { type: Function, default: null },
    debounceMs: { type: Number, default: 300 },
    // Flexible option format
    valueKey: { type: String, default: 'value' },
    labelKey: { type: String, default: 'label' },
    descriptionKey: { type: String, default: 'description' },
    // Custom label function for complex formatting (e.g., combining first_name + last_name)
    labelFn: { type: Function, default: null },
    // Custom description function for complex formatting (e.g., accessing nested user.email)
    descriptionFn: { type: Function, default: null },
    // Initial display value (for when modelValue is just an ID)
    initialDisplayValue: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'select']);

const inputRef = ref(null);
const containerRef = ref(null);
const dropdownRef = ref(null);
const searchQuery = ref('');
const asyncOptions = ref([]);
const isOpen = ref(false);
const isLoading = ref(false);
const highlightedIndex = ref(-1);
const selectedItem = ref(null);
const dropdownPosition = ref({ top: 0, left: 0, width: 0 });
let searchTimeout = null;

const dropdownStyle = computed(() => ({
    top: `${dropdownPosition.value.top}px`,
    left: `${dropdownPosition.value.left}px`,
    width: `${dropdownPosition.value.width}px`,
}));

function updateDropdownPosition() {
    if (!inputRef.value) return;
    const rect = inputRef.value.getBoundingClientRect();
    dropdownPosition.value = {
        top: rect.bottom + window.scrollY + 4,
        left: rect.left + window.scrollX,
        width: rect.width,
    };
}

const sizes = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-3 py-2 text-base',
    lg: 'px-3 py-3 text-lg',
};

const inputId = computed(() => `autocomplete-${Math.random().toString(36).substr(2, 9)}`);
const listboxId = computed(() => `${inputId.value}-listbox`);
const descriptionId = computed(() => (props.error || props.hint) ? `${inputId.value}-desc` : undefined);

const showMinCharsMessage = computed(() => {
    return props.minChars > 0 && searchQuery.value.length > 0 && searchQuery.value.length < props.minChars;
});

// Use async results if searchFn provided, otherwise filter local options
const filteredOptions = computed(() => {
    if (props.searchFn) {
        return asyncOptions.value;
    }

    if (!searchQuery.value || props.minChars > searchQuery.value.length) {
        return props.options;
    }

    const query = searchQuery.value.toLowerCase();
    return props.options.filter(option => {
        const label = getOptionLabel(option);
        const description = getOptionDescription(option);
        return label?.toLowerCase().includes(query) ||
               description?.toLowerCase().includes(query);
    });
});

// Support both object options and primitive options
function getOptionValue(option) {
    if (typeof option === 'object' && option !== null) {
        return option[props.valueKey] ?? option.id ?? option.value;
    }
    return option;
}

function getOptionLabel(option) {
    // Use custom labelFn if provided
    if (props.labelFn && typeof option === 'object' && option !== null) {
        return props.labelFn(option);
    }
    if (typeof option === 'object' && option !== null) {
        return option[props.labelKey] ?? option.text ?? option.name ?? option.label;
    }
    return String(option);
}

function getOptionDescription(option) {
    // Use custom descriptionFn if provided
    if (props.descriptionFn && typeof option === 'object' && option !== null) {
        return props.descriptionFn(option);
    }
    if (typeof option === 'object' && option !== null) {
        return option[props.descriptionKey] ?? option.desc ?? option.email;
    }
    return null;
}

// Initialize display value
onMounted(() => {
    if (props.initialDisplayValue) {
        searchQuery.value = props.initialDisplayValue;
        selectedItem.value = { _displayValue: props.initialDisplayValue };
    } else if (props.modelValue && props.options.length > 0) {
        const option = props.options.find(o => getOptionValue(o) === props.modelValue);
        if (option) {
            selectedItem.value = option;
            searchQuery.value = getOptionLabel(option);
        }
    }
});

// Watch for external modelValue changes
watch(() => props.modelValue, (newVal) => {
    if (!newVal) {
        selectedItem.value = null;
        searchQuery.value = '';
    }
});

function onInput() {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    // Clear selection when typing
    if (selectedItem.value) {
        selectedItem.value = null;
        emit('update:modelValue', null);
        emit('select', null);
    }

    isOpen.value = true;
    highlightedIndex.value = -1;

    // Async search with debounce
    if (props.searchFn && searchQuery.value.length >= props.minChars) {
        isLoading.value = true;
        searchTimeout = setTimeout(async () => {
            try {
                asyncOptions.value = await props.searchFn(searchQuery.value);
            } catch (error) {
                console.error('Search error:', error);
                asyncOptions.value = [];
            } finally {
                isLoading.value = false;
            }
        }, props.debounceMs);
    } else if (props.searchFn) {
        asyncOptions.value = [];
        isLoading.value = false;
    }
}

function onFocus() {
    isOpen.value = true;
    nextTick(updateDropdownPosition);
    if (props.searchFn && searchQuery.value.length >= props.minChars) {
        onInput();
    }
}

function onBlur() {
    setTimeout(() => {
        isOpen.value = false;
        // Restore selected item's display text if nothing new selected
        if (selectedItem.value) {
            searchQuery.value = selectedItem.value._displayValue || getOptionLabel(selectedItem.value);
        }
    }, 200);
}

function selectOption(option) {
    selectedItem.value = option;
    searchQuery.value = getOptionLabel(option);
    emit('update:modelValue', getOptionValue(option));
    emit('select', option);
    isOpen.value = false;
    asyncOptions.value = [];
}

function clearSelection() {
    selectedItem.value = null;
    searchQuery.value = '';
    emit('update:modelValue', null);
    emit('select', null);
    inputRef.value?.focus();
}

function closeDropdown() {
    isOpen.value = false;
}

function highlightNext() {
    if (filteredOptions.value.length === 0) return;
    highlightedIndex.value = (highlightedIndex.value + 1) % filteredOptions.value.length;
}

function highlightPrev() {
    if (filteredOptions.value.length === 0) return;
    highlightedIndex.value = highlightedIndex.value <= 0
        ? filteredOptions.value.length - 1
        : highlightedIndex.value - 1;
}

function selectHighlighted() {
    if (highlightedIndex.value >= 0 && highlightedIndex.value < filteredOptions.value.length) {
        selectOption(filteredOptions.value[highlightedIndex.value]);
    }
}

// Cleanup
onUnmounted(() => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
});
</script>
