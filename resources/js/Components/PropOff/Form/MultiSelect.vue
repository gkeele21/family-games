<template>
    <div>
        <FormLabel v-if="label" :id="labelId" :required="required">{{ label }}</FormLabel>

        <div class="relative" ref="containerRef">
            <!-- Trigger button -->
            <button
                ref="triggerRef"
                type="button"
                @click="toggleDropdown"
                :aria-expanded="isOpen"
                :aria-labelledby="label ? labelId : undefined"
                :aria-haspopup="true"
                :disabled="disabled"
                :class="[
                    'flex items-center justify-between w-full rounded border transition-all text-left',
                    'focus:outline-none focus-glow',
                    'bg-surface-elevated text-body',
                    error
                        ? 'border-danger focus:border-transparent'
                        : 'border-border focus:border-transparent',
                    disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
                    sizes[size]
                ]"
            >
                <span class="truncate">
                    {{ selectedCount > 0 ? `${selectedCount} selected` : placeholder }}
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
                        class="fixed z-[9999] bg-surface-elevated border border-border rounded shadow-lg max-h-60 overflow-auto"
                        :style="dropdownStyle"
                        @click.stop
                    >
                        <!-- Search filter -->
                        <div v-if="searchable" class="p-2 border-b border-border">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search..."
                                class="w-full px-2 py-1 text-sm border border-border rounded focus:outline-none focus:border-primary bg-surface text-body placeholder:text-subtle"
                            />
                        </div>

                        <!-- Select all option -->
                        <div v-if="showSelectAll" class="px-3 py-2 border-b border-border">
                            <label class="flex items-center cursor-pointer hover:text-primary">
                                <input
                                    type="checkbox"
                                    :checked="allSelected"
                                    :indeterminate="someSelected && !allSelected"
                                    @change="toggleAll"
                                    class="rounded border-border text-primary focus:ring-primary"
                                />
                                <span class="ml-2 text-sm font-medium">Select All</span>
                            </label>
                        </div>

                        <!-- Options list -->
                        <div class="py-1" role="listbox" :aria-multiselectable="true">
                            <template v-for="option in filteredOptions" :key="getOptionValue(option)">
                                <label
                                    :class="[
                                        'flex items-center px-3 py-2 cursor-pointer transition-colors',
                                        'hover:bg-surface',
                                        isSelected(option) ? 'bg-surface' : ''
                                    ]"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="isSelected(option)"
                                        @change="toggleOption(option)"
                                        class="rounded border-border text-primary focus:ring-primary"
                                    />
                                    <span class="ml-2 text-sm text-body">{{ getOptionLabel(option) }}</span>
                                    <span v-if="getOptionDescription(option)" class="ml-1 text-sm text-muted">
                                        ({{ getOptionDescription(option) }})
                                    </span>
                                </label>
                            </template>

                            <div v-if="filteredOptions.length === 0" class="px-3 py-2 text-sm text-muted">
                                No options found
                            </div>
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
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import Icon from '@/Components/PropOff/Base/Icon.vue';
import FormLabel from '@/Components/PropOff/Form/FormLabel.vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] },
    label: { type: String, default: '' },
    placeholder: { type: String, default: 'Select options...' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    size: { type: String, default: 'md' },
    searchable: { type: Boolean, default: false },
    showSelectAll: { type: Boolean, default: false },
    // Flexible option format
    valueKey: { type: String, default: 'value' },
    labelKey: { type: String, default: 'label' },
    descriptionKey: { type: String, default: 'description' },
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const searchQuery = ref('');
const containerRef = ref(null);
const triggerRef = ref(null);
const dropdownRef = ref(null);
const dropdownPosition = ref({ top: 0, left: 0, width: 0 });

const dropdownStyle = computed(() => ({
    top: `${dropdownPosition.value.top}px`,
    left: `${dropdownPosition.value.left}px`,
    width: `${dropdownPosition.value.width}px`,
}));

function toggleDropdown() {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        nextTick(updateDropdownPosition);
    }
}

function updateDropdownPosition() {
    if (!triggerRef.value) return;
    const rect = triggerRef.value.getBoundingClientRect();
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

const labelId = computed(() => `multiselect-label-${Math.random().toString(36).substr(2, 9)}`);

const selectedCount = computed(() => props.modelValue.length);

// Support both object options and primitive options
function getOptionValue(option) {
    if (typeof option === 'object' && option !== null) {
        return option[props.valueKey] ?? option.id ?? option.value;
    }
    return option;
}

function getOptionLabel(option) {
    if (typeof option === 'object' && option !== null) {
        return option[props.labelKey] ?? option.text ?? option.name ?? option.label;
    }
    return option;
}

function getOptionDescription(option) {
    if (typeof option === 'object' && option !== null) {
        return option[props.descriptionKey] ?? option.desc;
    }
    return null;
}

function isSelected(option) {
    const value = getOptionValue(option);
    return props.modelValue.includes(value);
}

function toggleOption(option) {
    const value = getOptionValue(option);
    const newValue = isSelected(option)
        ? props.modelValue.filter(v => v !== value)
        : [...props.modelValue, value];
    emit('update:modelValue', newValue);
}

const filteredOptions = computed(() => {
    if (!searchQuery.value) return props.options;
    const query = searchQuery.value.toLowerCase();
    return props.options.filter(option => {
        const label = getOptionLabel(option);
        return label?.toLowerCase().includes(query);
    });
});

const allSelected = computed(() => {
    return props.options.length > 0 && props.modelValue.length === props.options.length;
});

const someSelected = computed(() => {
    return props.modelValue.length > 0;
});

function toggleAll() {
    if (allSelected.value) {
        emit('update:modelValue', []);
    } else {
        emit('update:modelValue', props.options.map(getOptionValue));
    }
}

// Close on outside click
function handleClickOutside(event) {
    const clickedInContainer = containerRef.value && containerRef.value.contains(event.target);
    const clickedInDropdown = dropdownRef.value && dropdownRef.value.contains(event.target);
    if (!clickedInContainer && !clickedInDropdown) {
        isOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>
