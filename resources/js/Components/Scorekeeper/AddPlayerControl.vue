<script setup lang="ts">
import InputLabel from '@/Components/Scorekeeper/InputLabel.vue';
import SecondaryButton from '@/Components/Scorekeeper/SecondaryButton.vue';
import TextInput from '@/Components/Scorekeeper/TextInput.vue';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        availablePlayers: Array<{ id: number; name: string }>;
        /** People from the user's other households / friends (see
         *  Household::playerSuggestionsFor). Picked ones carry their
         *  account link. */
        suggestions?: Array<{
            name: string;
            user_id: number | null;
            source: string;
        }>;
        label?: string;
    }>(),
    { suggestions: () => [] },
);

const emit = defineEmits<{
    (
        e: 'add',
        payload: {
            player_id?: number;
            new_player_name?: string;
            user_id?: number | null;
            add_to_household?: boolean;
        },
    ): void;
}>();

const NEW = '__new__';
const selectValue = ref('');
const newName = ref('');
const addToHousehold = ref(false);

const isNew = computed(() => selectValue.value === NEW);
const suggestion = computed(() =>
    selectValue.value.startsWith('s:')
        ? props.suggestions[Number(selectValue.value.slice(2))]
        : null,
);
const canAdd = computed(() =>
    isNew.value ? newName.value.trim() !== '' : selectValue.value !== '',
);

// Suggestions are known people — saving them to the roster is the natural
// default; a typed-in name stays a one-off guest unless opted in.
const onSelectChange = () => {
    addToHousehold.value = suggestion.value !== null;
};

const submit = () => {
    if (!canAdd.value) return;
    if (isNew.value) {
        emit('add', {
            new_player_name: newName.value.trim(),
            add_to_household: addToHousehold.value,
        });
    } else if (suggestion.value) {
        emit('add', {
            new_player_name: suggestion.value.name,
            user_id: suggestion.value.user_id,
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
                class="rounded-lg border-border-strong bg-surface-inset text-body text-sm focus:border-primary focus:ring-primary"
                @change="onSelectChange"
            >
                <option value="">Add a player…</option>
                <option
                    v-for="p in availablePlayers"
                    :key="p.id"
                    :value="String(p.id)"
                >
                    {{ p.name }}
                </option>
                <optgroup
                    v-if="suggestions.length"
                    label="Other households & friends"
                >
                    <option
                        v-for="(s, i) in suggestions"
                        :key="`s-${i}`"
                        :value="`s:${i}`"
                    >
                        {{ s.name }} ({{ s.source }})
                    </option>
                </optgroup>
                <option :value="NEW">+ New player…</option>
            </select>
            <SecondaryButton
                v-if="!isNew && !suggestion"
                type="button"
                :disabled="!canAdd"
                @click="submit"
                >Add</SecondaryButton
            >
        </div>
        <div v-if="isNew || suggestion" class="flex flex-wrap items-center gap-3">
            <TextInput
                v-if="isNew"
                v-model="newName"
                type="text"
                class="text-sm"
                placeholder="New player name"
                @keydown.enter.prevent="submit"
            />
            <label class="flex items-center gap-1 text-sm text-body">
                <input
                    v-model="addToHousehold"
                    type="checkbox"
                    class="rounded border-border-strong bg-surface-inset text-primary focus:ring-primary"
                />
                Add to household roster
            </label>
            <SecondaryButton type="button" :disabled="!canAdd" @click="submit"
                >Add</SecondaryButton
            >
        </div>
    </div>
</template>
