<script setup lang="ts">
import GamePicker from '@/Components/Scorekeeper/GamePicker.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { ref, watch } from 'vue';

interface ScoreField {
    label: string;
    counts_toward_total: boolean;
    color?: string | null;
}

interface TemplateForm {
    name: string;
    game_type_id: number | null;
    new_game_name: string | null;
    target_score: number | null;
    max_rounds: number | null;
    low_score_wins: boolean;
    team_based: boolean;
    allow_self_scoring: boolean;
    is_global: boolean;
    score_fields: ScoreField[];
    errors: Record<string, string | undefined>;
}

const props = defineProps<{
    form: TemplateForm;
    games: Array<{ id: number; name: string }>;
    idPrefix?: string;
}>();

const fid = (name: string) => `${props.idPrefix ?? 'tpl'}-${name}`;

// Auto-suggest the template name from the chosen game. Only fills the name
// while it's empty or still our own suggestion — never a hand-typed name.
const lastSuggestion = ref('');
watch(
    () => [props.form.game_type_id, props.form.new_game_name],
    () => {
        const gameName =
            props.form.new_game_name?.trim() ||
            props.games.find((g) => g.id === props.form.game_type_id)?.name ||
            '';
        if (!gameName) return;
        if (
            props.form.name === '' ||
            props.form.name === lastSuggestion.value
        ) {
            props.form.name = gameName;
            lastSuggestion.value = gameName;
        }
    },
);

const addField = () => {
    props.form.score_fields.push({
        label: '',
        counts_toward_total: true,
        color: null,
    });
};

const removeField = (index: number) => {
    props.form.score_fields.splice(index, 1);
};
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <GamePicker
                    :id="fid('game')"
                    :games="games"
                    v-model:game-type-id="form.game_type_id"
                    v-model:new-name="form.new_game_name"
                />
                <InputError
                    class="mt-1"
                    :message="form.errors.game_type_id ?? form.errors.new_game_name"
                />
            </div>
            <div>
                <InputLabel :for="fid('name')" value="Template name" />
                <TextInput
                    :id="fid('name')"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="e.g. Hearts to 50"
                />
                <p class="mt-1 text-xs text-gray-500">
                    A game can have more than one template — name this set of
                    rules.
                </p>
                <InputError class="mt-1" :message="form.errors.name" />
            </div>
            <div>
                <InputLabel
                    :for="fid('target')"
                    value="Target score (optional)"
                />
                <input
                    :id="fid('target')"
                    v-model.number="form.target_score"
                    type="number"
                    min="0"
                    class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <InputError class="mt-1" :message="form.errors.target_score" />
            </div>
            <div>
                <InputLabel
                    :for="fid('rounds')"
                    value="Max rounds (optional)"
                />
                <input
                    :id="fid('rounds')"
                    v-model.number="form.max_rounds"
                    type="number"
                    min="1"
                    class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <InputError class="mt-1" :message="form.errors.max_rounds" />
            </div>
        </div>

        <label class="flex items-center gap-2">
            <input
                v-model="form.low_score_wins"
                type="checkbox"
                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
            />
            <span class="text-sm text-gray-700">Lowest score wins</span>
        </label>

        <label class="flex items-center gap-2">
            <input
                v-model="form.team_based"
                type="checkbox"
                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
            />
            <span class="text-sm text-gray-700">Played in teams</span>
        </label>

        <label class="flex items-center gap-2">
            <input
                v-model="form.allow_self_scoring"
                type="checkbox"
                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
            />
            <span class="text-sm text-gray-700"
                >Players can enter their own scores
                <span class="text-xs text-gray-400"
                    >(invited players with accounts edit only their own
                    column)</span
                ></span
            >
        </label>

        <label class="flex items-center gap-2">
            <input
                v-model="form.is_global"
                type="checkbox"
                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
            />
            <span class="text-sm text-gray-700"
                >Share with all households</span
            >
        </label>

        <!-- Score fields -->
        <div>
            <InputLabel value="Scores tracked each round" />
            <p class="mb-2 text-xs text-gray-500">
                Add a field for each number you record per round. Only fields
                marked "counts toward total" add up to decide the winner.
            </p>
            <div class="space-y-2">
                <div
                    v-for="(field, i) in form.score_fields"
                    :key="i"
                    class="flex items-center gap-3"
                >
                    <div class="flex shrink-0 items-center gap-1">
                        <input
                            type="color"
                            class="h-8 w-8 cursor-pointer rounded border border-gray-300 bg-white p-0.5"
                            :value="field.color ?? '#cccccc'"
                            title="Score color (optional)"
                            @input="
                                field.color = (
                                    $event.target as HTMLInputElement
                                ).value
                            "
                        />
                        <button
                            v-if="field.color"
                            type="button"
                            class="text-xs text-gray-400 hover:text-gray-600"
                            title="Clear color"
                            @click="field.color = null"
                        >
                            clear
                        </button>
                    </div>
                    <TextInput
                        v-model="field.label"
                        type="text"
                        class="block w-full"
                        placeholder="e.g. base, base2, points"
                    />
                    <label
                        class="flex shrink-0 items-center gap-1 text-sm text-gray-700"
                    >
                        <input
                            v-model="field.counts_toward_total"
                            type="checkbox"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        counts toward total
                    </label>
                    <button
                        v-if="form.score_fields.length > 1"
                        type="button"
                        class="shrink-0 text-sm text-red-600 hover:text-red-500"
                        @click="removeField(i)"
                    >
                        Remove
                    </button>
                </div>
            </div>
            <button
                type="button"
                class="mt-2 text-sm font-medium text-[#0b5d3b] hover:text-[#084a2f]"
                @click="addField"
            >
                + Add score field
            </button>
            <InputError class="mt-1" :message="form.errors.score_fields" />
        </div>
    </div>
</template>
