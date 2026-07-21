<template>
    <th @click="$emit('sort')" class="cursor-pointer">
        <slot>{{ label }}</slot>
        <Icon
            :name="iconName"
            size="xs"
            :class="['ml-1', isActive ? 'text-black' : 'text-muted']"
        />
    </th>
</template>

<script setup>
import { computed } from 'vue';
import Icon from '@/Components/Base/Icon.vue';

const props = defineProps({
    sort: { type: Object, required: true }, // { field, order }
    field: { type: [String, Function], required: true },
    label: { type: String, default: '' },
});

defineEmits(['sort']);

const fieldKey = computed(() => {
    return typeof props.field === 'function'
        ? props.field.toString()
        : props.field;
});

const isActive = computed(() => props.sort.field === fieldKey.value);

const iconName = computed(() => {
    if (!isActive.value) return 'sort';
    return props.sort.order === 'asc' ? 'sort-up' : 'sort-down';
});
</script>
