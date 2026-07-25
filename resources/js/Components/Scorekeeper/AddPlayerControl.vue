<script setup lang="ts">
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { computed, ref } from 'vue';

defineProps<{
    availablePlayers: Array<{ id: number; name: string }>;
    label?: string;
}>();

const emit = defineEmits<{
    (
        e: 'add',
        payload: {
            player_id?: number;
            new_player_name?: string;
            add_to_household?: boolean;
        },
    ): void;
}>();

const NEW = '__new__';
const selectValue = ref('');
const newName = ref('');
const addToHousehold = ref(false);

const isNew = computed(() => selectValue.value === NEW);
const canAdd = computed(() =>
    isNew.value ? newName.value.trim() !== '' : selectValue.value !== '',
);

const submit = () => {
    if (!canAdd.value) return;
    if (isNew.value) {
        emit('add', {
            new_player_name: newName.value.trim(),
            add_to_household: addToHousehold.value,
        });
    } else {
        emit('add', { player_id: Number(selectValue.value) });
    }
    selectValue.value = '';
    newName.value = '';
    addToHousehold.value = false;
};
</script>

<template>
    <div class="space-y-2">
        <InputLabel v-if="label" :value="label" />
        <div class="flex items-center gap-2">
            <select
                v-model="selectValue"
                class="rounded-md border-gray-300 bg-white text-gray-900 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option value="">Add a player…</option>
                <option
                    v-for="p in availablePlayers"
                    :key="p.id"
                    :value="String(p.id)"
                >
                    {{ p.name }}
                </option>
                <option :value="NEW">+ New player…</option>
            </select>
            <SecondaryButton
                v-if="!isNew"
                type="button"
                :disabled="!canAdd"
                @click="submit"
                >Add</SecondaryButton
            >
        </div>
        <div v-if="isNew" class="flex flex-wrap items-center gap-3">
            <TextInput
                v-model="newName"
                type="text"
                class="text-sm"
                placeholder="New player name"
                @keydown.enter.prevent="submit"
            />
            <label class="flex items-center gap-1 text-sm text-gray-700">
                <input
                    v-model="addToHousehold"
                    type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                />
                Add to household roster
            </label>
            <SecondaryButton type="button" :disabled="!canAdd" @click="submit"
                >Add</SecondaryButton
            >
        </div>
    </div>
</template>
