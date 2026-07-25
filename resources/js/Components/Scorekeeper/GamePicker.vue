<script setup lang="ts">
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { ref, watch } from 'vue';

const props = defineProps<{
    games: Array<{ id: number; name: string }>;
    gameTypeId: number | null;
    newName: string | null;
    id?: string;
}>();

const emit = defineEmits<{
    (e: 'update:gameTypeId', value: number | null): void;
    (e: 'update:newName', value: string | null): void;
}>();

const NEW = '__new__';

function derivedSelect(): string {
    if (props.newName) return NEW;
    if (props.gameTypeId != null) return String(props.gameTypeId);
    return '';
}

const selectValue = ref<string>(derivedSelect());
const newText = ref<string>(props.newName ?? '');

// Guard so our own emits aren't re-derived as external changes.
let internal = false;

const onSelectChange = () => {
    internal = true;
    if (selectValue.value === NEW) {
        emit('update:gameTypeId', null);
        emit('update:newName', newText.value || null);
    } else if (selectValue.value === '') {
        emit('update:gameTypeId', null);
        emit('update:newName', null);
    } else {
        emit('update:gameTypeId', Number(selectValue.value));
        emit('update:newName', null);
    }
};

const backToList = () => {
    internal = true;
    selectValue.value = '';
    newText.value = '';
    emit('update:gameTypeId', null);
    emit('update:newName', null);
};

watch(newText, () => {
    if (selectValue.value !== NEW) return;
    internal = true;
    emit('update:newName', newText.value || null);
});

watch(
    () => [props.gameTypeId, props.newName],
    () => {
        if (internal) {
            internal = false;
            return;
        }
        selectValue.value = derivedSelect();
        newText.value = props.newName ?? '';
    },
);
</script>

<template>
    <div>
        <InputLabel :for="id" value="Game" />

        <select
            v-if="selectValue !== NEW"
            :id="id"
            v-model="selectValue"
            class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            @change="onSelectChange"
        >
            <option value="" disabled>Select a game…</option>
            <option v-for="g in games" :key="g.id" :value="String(g.id)">
                {{ g.name }}
            </option>
            <option :value="NEW">+ Add a new game…</option>
        </select>

        <div v-else class="mt-1 flex items-center gap-2">
            <TextInput
                :id="id"
                v-model="newText"
                type="text"
                class="block w-full"
                placeholder="New game name"
            />
            <button
                type="button"
                class="whitespace-nowrap text-sm text-gray-500 hover:text-gray-700"
                @click="backToList"
            >
                Cancel
            </button>
        </div>
    </div>
</template>
